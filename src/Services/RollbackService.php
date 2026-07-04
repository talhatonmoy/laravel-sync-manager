<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Contracts\RollbackServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\SecurityGateInterface;
use DeployCar\LaravelSyncManager\Contracts\StateRepositoryInterface;
use DeployCar\LaravelSyncManager\Contracts\VersionManagerInterface;
use DeployCar\LaravelSyncManager\Models\SyncVersion;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class RollbackService implements RollbackServiceInterface
{
    public function __construct(
        protected Filesystem $files,
        protected VersionManagerInterface $versionManager,
        protected StateRepositoryInterface $stateRepository,
        protected ObjectStoreInterface $objectStore,
        protected BackupManager $backupManager,
        protected LockManager $lockManager,
        protected PathSecurity $pathSecurity,
        protected SecurityGateInterface $securityGate
    ) {
    }

    public function rollbackTo(?string $versionId = null, ?callable $progress = null, ?string $operationId = null): array
    {
        $this->report($progress, 10, 'locating-version', 'Locating the target version for rollback.');
        $targetVersion = $versionId
            ? SyncVersion::query()->where('version_id', $versionId)->first()
            : $this->versionManager->latestSuccessful('sync');

        if (! $targetVersion) {
            throw new RuntimeException('Rollback target version could not be found.');
        }

        return $this->performRollback($targetVersion, $progress, $operationId);
    }

    public function undoLastSync(?callable $progress = null, ?string $operationId = null): array
    {
        $this->report($progress, 10, 'locating-version', 'Finding the previous successful sync state.');
        $targetVersion = SyncVersion::query()
            ->where('operation', 'sync')
            ->where('status', 'success')
            ->latest('id')
            ->skip(1)
            ->first();

        if (! $targetVersion) {
            throw new RuntimeException('No previous sync version is available to undo to.');
        }

        return $this->performRollback($targetVersion, $progress, $operationId);
    }

    protected function performRollback(SyncVersion $targetVersion, ?callable $progress = null, ?string $operationId = null): array
    {
        return $this->lockManager->run('rollback', function () use ($targetVersion, $progress) {
            if ($targetVersion->files()->count() === 0) {
                throw new RuntimeException('Rollback version does not contain a tracked state snapshot.');
            }

            $operationVersion = $this->versionManager->createVersion([
                'version_id' => (string) Str::ulid(),
                'operation' => 'rollback',
                'direction' => 'incoming',
                'status' => 'running',
                'source_app' => 'rollback',
                'target_name' => $targetVersion->target_name ?: config('sync.target.name'),
                'summary' => $targetVersion->summary,
                'metadata' => [
                    'engine' => 'incremental',
                    'rollback_to' => $targetVersion->version_id,
                    'manifest_id' => data_get($targetVersion->metadata, 'manifest_id'),
                ],
            ]);

            $filePaths = $targetVersion->files()->pluck('path')->all();
            $this->report($progress, 35, 'backing-up-current', 'Backing up current files before rollback.');
            $backup = $this->backupManager->backupFiles($operationVersion->version_id, $filePaths);

            try {
                $this->report($progress, 70, 'restoring-files', 'Restoring tracked files from the object store.');
                foreach ($targetVersion->files as $file) {
                    if (! $file->hash || ! $this->objectStore->has($file->hash)) {
                        throw new RuntimeException("Rollback object is missing for [{$file->path}].");
                    }

                    $relativePath = $this->pathSecurity->assertSafe($file->path);
                    $this->securityGate->assertSafe($relativePath);
                    $destinationPath = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
                    $this->files->ensureDirectoryExists(dirname($destinationPath));
                    $this->files->copy($this->objectStore->path($file->hash), $destinationPath);
                }

                $state = [];
                foreach ($targetVersion->files as $file) {
                    $state[$file->path] = [
                        'hash' => $file->hash,
                        'size' => $file->size,
                        'modified_at' => optional($file->modified_at)->toIso8601String(),
                    ];
                }

                $this->stateRepository->replace(
                    $targetVersion->target_name ?: config('sync.target.name'),
                    $state,
                    data_get($targetVersion->metadata, 'manifest_id')
                );
                $this->versionManager->replaceFiles($operationVersion, $targetVersion->files()->get()->map(fn ($file) => [
                    'path' => $file->path,
                    'hash' => $file->hash,
                    'size' => $file->size,
                    'modified_at' => optional($file->modified_at)->toIso8601String(),
                    'status' => 'restored',
                ])->all());
                $this->versionManager->updateStatus($operationVersion, 'success', [
                    'applied_at' => now(),
                    'completed_at' => now(),
                    'metadata' => array_merge($operationVersion->metadata ?? [], [
                        'backup' => $backup,
                    ]),
                ]);
                $targetVersion->forceFill(['rolled_back_at' => now()])->save();

                $this->report($progress, 100, 'completed', 'Incremental rollback completed successfully.');

                return [
                    'status' => 'success',
                    'version_id' => $operationVersion->version_id,
                    'rollback_to' => $targetVersion->version_id,
                    'engine' => 'incremental',
                ];
            } catch (\Throwable $exception) {
                $this->restoreBackups($backup['backup_root'].'/files');
                $this->versionManager->updateStatus($operationVersion, 'failed', [
                    'metadata' => array_merge($operationVersion->metadata ?? [], [
                        'error' => $exception->getMessage(),
                    ]),
                ]);

                throw $exception;
            }
        });
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
