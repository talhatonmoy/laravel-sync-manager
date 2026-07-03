<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\ChangeDetectorInterface;
use DeployCar\LaravelSyncManager\Contracts\FileScannerInterface;
use DeployCar\LaravelSyncManager\Contracts\IncrementalTransportInterface;
use DeployCar\LaravelSyncManager\Contracts\LocalBackupServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\ManifestRepositoryInterface;
use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Contracts\ProductionPullServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\SecurityGateInterface;
use DeployCar\LaravelSyncManager\Contracts\StateRepositoryInterface;
use DeployCar\LaravelSyncManager\Contracts\VersionManagerInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class ProductionPullService implements ProductionPullServiceInterface
{
    public function __construct(
        protected FileScannerInterface $scanner,
        protected ChangeDetectorInterface $changeDetector,
        protected IncrementalTransportInterface $transport,
        protected ObjectStoreInterface $objectStore,
        protected StateRepositoryInterface $stateRepository,
        protected ManifestRepositoryInterface $manifestRepository,
        protected VersionManagerInterface $versionManager,
        protected LocalBackupServiceInterface $localBackupService,
        protected OperationTrackerInterface $operationTracker,
        protected NotificationService $notificationService,
        protected Filesystem $files,
        protected PathSecurity $pathSecurity,
        protected SecurityGateInterface $securityGate,
        protected TargetResolver $targetResolver
    ) {
    }

    public function preview(?string $targetName = null, string $strategy = 'production-first', ?callable $progress = null): array
    {
        $target = $this->resolveTarget($targetName);
        $this->report($progress, 10, 'fetching-state', 'Fetching the current production state.');
        $remote = $this->transport->fetchState($target);
        $this->report($progress, 45, 'scanning-local', 'Scanning the local project for comparison.');
        $localFiles = $this->scanner->scan();
        $preview = $this->changeDetector->preview($localFiles, $remote['files'] ?? []);
        $this->report($progress, 100, 'completed', 'Production-first preview completed.');

        return array_merge($preview, [
            'status' => 'success',
            'strategy' => $strategy,
            'target' => $target['name'],
            'manifest_id' => $remote['manifest_id'] ?? null,
            'remote_state' => $remote['files'] ?? [],
            'engine' => 'incremental',
        ]);
    }

    public function pull(?string $targetName = null, ?callable $progress = null, ?string $operationId = null): array
    {
        $target = $this->resolveTarget($targetName);
        $preview = $this->preview($target['name'], 'production-first', $progress);
        $remoteState = $preview['remote_state'] ?? [];
        $affectedPaths = array_values(array_unique(array_merge(
            array_column($preview['overwrite_local'], 'path'),
            array_column($preview['production_only'], 'path')
        )));

        if ($affectedPaths === []) {
            $this->report($progress, 100, 'completed', 'Local already matches production.');

            return [
                'status' => 'success',
                'target' => $target['name'],
                'summary' => $preview['summary'],
                'message' => 'Local already matches production.',
                'engine' => 'incremental',
            ];
        }

        $versionId = (string) Str::ulid();
        $manifestId = (string) ($preview['manifest_id'] ?? Str::ulid());
        $this->report($progress, 35, 'backing-up-local', 'Creating a local backup before applying production files.');
        $localBackup = $this->localBackupService->backupFiles($versionId, $affectedPaths);

        $version = $this->versionManager->createVersion([
            'version_id' => $versionId,
            'operation' => 'pull',
            'direction' => 'incoming',
            'status' => 'running',
            'source_app' => $target['name'],
            'target_name' => $target['name'],
            'summary' => $preview['summary'],
            'metadata' => [
                'engine' => 'incremental',
                'strategy' => 'production-first',
                'source_side' => 'production',
                'apply_direction' => 'production_to_local',
                'confirmation_state' => 'applied',
                'backup_scope' => $affectedPaths,
                'manifest_id' => $manifestId,
            ],
        ]);

        if ($operationId) {
            $this->operationTracker->attachVersion($operationId, $version->id);
        }

        try {
            $changes = [];
            $this->report($progress, 60, 'downloading-objects', 'Downloading only the changed file objects from production.');

            foreach ($affectedPaths as $path) {
                $remote = $remoteState[$path] ?? null;
                if (! $remote) {
                    continue;
                }

                $contents = $this->transport->downloadObject($target, (string) $remote['hash']);
                $this->objectStore->storeContents($contents, (string) $remote['hash']);
                $changes[] = [
                    'path' => $path,
                    'hash' => $remote['hash'],
                    'size' => $remote['size'] ?? null,
                    'modified_at' => $remote['modified_at'] ?? now()->toIso8601String(),
                    'status' => 'pulled_from_production',
                ];
            }

            $this->report($progress, 82, 'applying-files', 'Applying the changed production files to local.');
            foreach ($changes as $file) {
                $relativePath = $this->pathSecurity->assertSafe($file['path']);
                $this->securityGate->assertSafe($relativePath);
                $destinationPath = $this->sourceRoot().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $this->files->ensureDirectoryExists(dirname($destinationPath));
                $this->files->copy($this->objectStore->path($file['hash']), $destinationPath);
            }

            $this->manifestRepository->create([
                'manifest_id' => $manifestId,
                'target_name' => $target['name'],
                'direction' => 'incoming',
                'parent_manifest_id' => $this->stateRepository->latestManifestId($target['name']),
                'sync_version_id' => $version->id,
                'summary' => $preview['summary'],
                'metadata' => [
                    'source_app' => $target['name'],
                ],
            ], $changes);

            $this->stateRepository->replace($target['name'], $remoteState, $manifestId);
            $this->versionManager->replaceFiles($version, $this->snapshotFiles($remoteState));
            $this->versionManager->updateStatus($version, 'success', [
                'applied_at' => now(),
                'completed_at' => now(),
                'metadata' => array_merge($version->metadata ?? [], [
                    'local_backup' => $localBackup,
                ]),
            ]);

            $this->report($progress, 100, 'completed', 'Production-first pull completed.');
            $this->notificationService->notify('DeployCar production pull succeeded', [
                'version_id' => $versionId,
                'target' => $target['name'],
                'summary' => $preview['summary'],
            ]);

            return [
                'status' => 'success',
                'version_id' => $versionId,
                'manifest_id' => $manifestId,
                'target' => $target['name'],
                'summary' => $preview['summary'],
                'engine' => 'incremental',
            ];
        } catch (\Throwable $exception) {
            $this->report($progress, 90, 'restoring-local', 'Pull failed. Restoring the local backup.');
            $this->localBackupService->restore($localBackup['backup_root']);
            $this->versionManager->updateStatus($version, 'failed', [
                'metadata' => array_merge($version->metadata ?? [], [
                    'local_backup' => $localBackup,
                    'error' => $exception->getMessage(),
                ]),
            ]);
            $this->notificationService->notify('DeployCar production pull failed', [
                'version_id' => $versionId,
                'target' => $target['name'],
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
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

    protected function resolveTarget(?string $targetName = null): array
    {
        $target = $targetName ? $this->targetResolver->find($targetName) : $this->targetResolver->first();

        if (! $target) {
            throw new RuntimeException('No sync target is configured.');
        }

        return $target;
    }

    protected function sourceRoot(): string
    {
        return rtrim((string) config('sync.source_path', base_path()), DIRECTORY_SEPARATOR);
    }

    protected function report(?callable $progress, int $percent, string $stage, string $message): void
    {
        if ($progress) {
            $progress($percent, $stage, $message, []);
        }
    }
}
