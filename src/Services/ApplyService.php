<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\ApplyServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\ManifestRepositoryInterface;
use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Contracts\SecurityGateInterface;
use DeployCar\LaravelSyncManager\Contracts\StateRepositoryInterface;
use DeployCar\LaravelSyncManager\Contracts\VersionManagerInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class ApplyService implements ApplyServiceInterface
{
    public function __construct(
        protected Filesystem $files,
        protected ObjectStoreInterface $objectStore,
        protected StateRepositoryInterface $stateRepository,
        protected ManifestRepositoryInterface $manifestRepository,
        protected VersionManagerInterface $versionManager,
        protected BackupManager $backupManager,
        protected LockManager $lockManager,
        protected PathSecurity $pathSecurity,
        protected SecurityGateInterface $securityGate
    ) {
    }

    public function commit(array $manifest, ?callable $progress = null): array
    {
        return $this->lockManager->run('receive', function () use ($manifest, $progress) {
            $targetName = (string) ($manifest['target_name'] ?? config('sync.target.name'));
            $expectedState = $manifest['expected_target'] ?? [];
            $files = $manifest['files'] ?? [];
            $version = $this->versionManager->createVersion([
                'version_id' => (string) ($manifest['version_id'] ?? Str::ulid()),
                'operation' => 'sync',
                'direction' => 'incoming',
                'status' => 'running',
                'source_app' => $manifest['source_app'] ?? 'unknown',
                'target_name' => $targetName,
                'summary' => $manifest['summary'] ?? [],
                'metadata' => [
                    'engine' => 'incremental',
                    'manifest_id' => $manifest['manifest_id'] ?? null,
                    'parent_manifest_id' => $manifest['parent_manifest_id'] ?? null,
                    'signature' => $manifest['signature'] ?? null,
                ],
            ]);

            try {
                $this->report($progress, 10, 'verifying', 'Validating manifest structure and object availability.');
                foreach ($files as $file) {
                    $relativePath = $this->pathSecurity->assertSafe((string) $file['path']);

                    if ($file['status'] === 'delete_later') {
                        continue;
                    }

                    if (! $this->objectStore->has((string) $file['hash'])) {
                        throw new RuntimeException("Required object [{$file['hash']}] for [{$relativePath}] is missing.");
                    }
                }

                $currentState = $this->resolveCurrentState($targetName);
                $this->guardAgainstConflicts($files, $expectedState, $currentState);

                $backup = $this->backupManager->backupFiles($version->version_id, array_map(static fn (array $file) => $file['path'], $files));
                $this->report($progress, 45, 'staging', 'Writing changed files to staging area.');

                $stagingRoot = rtrim((string) config('sync.storage_root'), DIRECTORY_SEPARATOR)
                    .'/staging/'.$version->version_id;

                // Phase 1 — stage ALL files before touching destination.
                foreach ($files as $file) {
                    $path = $this->pathSecurity->assertSafe((string) $file['path']);
                    $this->securityGate->assertSafe($path);

                    if (($file['status'] ?? '') === 'delete_later') {
                        continue;
                    }

                    $stagingPath = $stagingRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $path);
                    $this->files->ensureDirectoryExists(dirname($stagingPath));
                    $this->files->copy($this->objectStore->path((string) $file['hash']), $stagingPath);
                }

                // Phase 2 — atomic rename swap: staging → destination (crash-safe).
                foreach ($files as $file) {
                    $path = $this->pathSecurity->assertSafe((string) $file['path']);
                    $destination = base_path(str_replace('/', DIRECTORY_SEPARATOR, $path));

                    if (($file['status'] ?? '') === 'delete_later') {
                        if ($this->files->exists($destination)) {
                            $this->files->delete($destination);
                        }

                        continue;
                    }

                    $stagingPath = $stagingRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $path);
                    $this->files->ensureDirectoryExists(dirname($destination));
                    $this->files->move($stagingPath, $destination);
                }

                // Clean up staging dir.
                if ($this->files->isDirectory($stagingRoot)) {
                    $this->files->deleteDirectory($stagingRoot);
                }

                $manifestRecord = $this->manifestRepository->create([
                    'manifest_id' => (string) $manifest['manifest_id'],
                    'target_name' => $targetName,
                    'direction' => 'incoming',
                    'parent_manifest_id' => $manifest['parent_manifest_id'] ?? null,
                    'sync_version_id' => $version->id,
                    'signature' => $manifest['signature'] ?? null,
                    'summary' => $manifest['summary'] ?? [],
                    'metadata' => [
                        'source_app' => $manifest['source_app'] ?? null,
                    ],
                ], $files);

                foreach ($files as $file) {
                    if (($file['status'] ?? '') === 'delete_later') {
                        unset($currentState[$file['path']]);
                    } else {
                        $currentState[$file['path']] = [
                            'hash' => $file['hash'],
                            'size' => $file['size'] ?? null,
                            'modified_at' => $file['modified_at'] ?? now()->toIso8601String(),
                        ];
                    }
                }

                $this->stateRepository->replace($targetName, $currentState, $manifestRecord->manifest_id);
                $this->versionManager->replaceFiles($version, $this->snapshotFiles($currentState));
                $this->versionManager->updateStatus($version, 'success', [
                    'applied_at' => now(),
                    'metadata' => array_merge($version->metadata ?? [], [
                        'backup' => $backup,
                    ]),
                ]);

                $this->report($progress, 100, 'completed', 'Incremental changes committed successfully.');

                return [
                    'status' => 'success',
                    'version_id' => $version->version_id,
                    'manifest_id' => $manifestRecord->manifest_id,
                    'target' => $targetName,
                ];
            } catch (\Throwable $exception) {
                // Clean up staging dir on failure.
                if (isset($stagingRoot) && $this->files->isDirectory($stagingRoot)) {
                    $this->files->deleteDirectory($stagingRoot);
                }

                if (isset($backup['backup_root'])) {
                    $this->restoreBackups($backup['backup_root'].'/files');
                }
                $this->versionManager->updateStatus($version, 'failed', [
                    'metadata' => array_merge($version->metadata ?? [], [
                        'error' => $exception->getMessage(),
                    ]),
                ]);

                throw $exception;
            }
        });
    }

    protected function resolveCurrentState(string $targetName): array
    {
        $tracked = $this->stateRepository->forTarget($targetName);
        if ($tracked !== []) {
            return $tracked;
        }

        $files = app(FileScanner::class)->scan(base_path());

        return collect($files)->mapWithKeys(static fn (array $file) => [
            $file['path'] => [
                'hash' => $file['hash'],
                'size' => $file['size'],
                'modified_at' => $file['modified_at'],
            ],
        ])->all();
    }

    protected function guardAgainstConflicts(array $files, array $expectedState, array $currentState): void
    {
        if (! config('sync.advanced.conflict_detection', false)) {
            return;
        }

        foreach ($files as $file) {
            $path = $file['path'];
            $expectedHash = $expectedState[$path]['hash'] ?? null;
            $currentHash = $currentState[$path]['hash'] ?? null;

            if ($expectedHash !== null && $expectedHash !== $currentHash) {
                throw new RuntimeException("Conflict detected for [{$path}].");
            }
        }
    }

    protected function snapshotFiles(array $state): array
    {
        ksort($state);

        return array_map(static fn (string $path, array $file) => [
            'path' => $path,
            'hash' => $file['hash'],
            'size' => $file['size'] ?? null,
            'modified_at' => $file['modified_at'] ?? null,
            'status' => 'synced',
        ], array_keys($state), array_values($state));
    }

    protected function restoreBackups(string $backupFilesRoot): void
    {
        if (! $this->files->isDirectory($backupFilesRoot)) {
            return;
        }

        foreach ($this->files->allFiles($backupFilesRoot, true) as $file) {
            $relativePath = ltrim(str_replace($backupFilesRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $destinationPath = base_path($relativePath);
            $this->files->ensureDirectoryExists(dirname($destinationPath));
            $this->files->copy($file->getPathname(), $destinationPath);
        }
    }

    protected function report(?callable $progress, int $percent, string $stage, string $message): void
    {
        if ($progress) {
            $progress($percent, $stage, $message, []);
        }
    }
}
