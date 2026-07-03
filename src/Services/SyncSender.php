<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\ChangeDetectorInterface;
use DeployCar\LaravelSyncManager\Contracts\FileScannerInterface;
use DeployCar\LaravelSyncManager\Contracts\IncrementalTransportInterface;
use DeployCar\LaravelSyncManager\Contracts\ManifestRepositoryInterface;
use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Contracts\StateRepositoryInterface;
use DeployCar\LaravelSyncManager\Contracts\SyncSenderInterface;
use DeployCar\LaravelSyncManager\Contracts\VersionManagerInterface;
use DeployCar\LaravelSyncManager\Jobs\ExecuteSyncOperationJob;
use Illuminate\Support\Str;
use RuntimeException;

class SyncSender implements SyncSenderInterface
{
    public function __construct(
        protected FileScannerInterface $scanner,
        protected ChangeDetectorInterface $changeDetector,
        protected IncrementalTransportInterface $transport,
        protected ObjectStoreInterface $objectStore,
        protected StateRepositoryInterface $stateRepository,
        protected ManifestRepositoryInterface $manifestRepository,
        protected VersionManagerInterface $versionManager,
        protected TargetResolver $targetResolver,
        protected OperationTrackerInterface $operationTracker,
        protected NotificationService $notificationService
    ) {
    }

    public function dryRun(?string $targetName = null, ?callable $progress = null): array
    {
        $target = $this->resolveTarget($targetName);
        $this->report($progress, 10, 'fetching-state', 'Fetching the current target state.');
        $remoteState = $this->transport->fetchState($target);
        $this->report($progress, 45, 'scanning-local', 'Scanning local files and hashing content.');
        $localFiles = $this->scanner->scan();
        $this->report($progress, 80, 'diffing', 'Comparing local files with the tracked target state.');
        $result = array_merge(
            $this->changeDetector->detect($localFiles, $remoteState['files'] ?? []),
            [
                'target' => $target['name'],
                'manifest_id' => $remoteState['manifest_id'] ?? null,
                'engine' => 'incremental',
            ]
        );
        $this->report($progress, 100, 'completed', 'Incremental preview completed.');

        return $result;
    }

    public function dryRunAll(): array
    {
        $results = [];

        foreach ($this->targetResolver->all() as $target) {
            $results[] = $this->dryRun($target['name']);
        }

        return [
            'status' => 'success',
            'targets' => $results,
        ];
    }

    public function send(?string $targetName = null, ?callable $progress = null, ?string $operationId = null): array
    {
        return $this->sendToTarget($this->resolveTarget($targetName), $progress, $operationId);
    }

    public function sendAll(): array
    {
        $results = [];

        foreach ($this->targetResolver->all() as $target) {
            $results[] = $this->sendToTarget($target);
        }

        return [
            'status' => collect($results)->contains(fn (array $result) => $result['status'] !== 'success') ? 'partial' : 'success',
            'targets' => $results,
        ];
    }

    public function dispatch(bool $all = false, ?string $targetName = null): array
    {
        $targets = $all ? $this->targetResolver->all() : [$this->resolveTarget($targetName)];
        $queued = [];

        foreach ($targets as $target) {
            $operation = $this->operationTracker->start([
                'type' => 'apply-local-first',
                'strategy' => 'local-first',
                'target_name' => $target['name'],
                'status' => 'queued',
                'message' => 'Queued incremental local-first sync.',
                'metadata' => [
                    'engine' => 'incremental',
                    'queued_via' => 'cli',
                ],
            ]);

            ExecuteSyncOperationJob::dispatchConfigured(
                $operation->operation_id,
                'apply-local-first',
                ['target' => $target['name']]
            );

            $queued[] = [
                'target' => $target['name'],
                'operation_id' => $operation->operation_id,
            ];
        }

        return [
            'status' => 'queued',
            'targets' => $queued,
        ];
    }

    public function sendToTarget(array $target, ?callable $progress = null, ?string $operationId = null): array
    {
        $this->report($progress, 5, 'fetching-state', 'Fetching the current target state.');
        $remoteState = $this->transport->fetchState($target);
        $expectedState = $remoteState['files'] ?? [];

        $this->report($progress, 20, 'scanning-local', 'Scanning local files and preparing changed blobs.');
        $localFiles = $this->scanner->scan();
        $diff = $this->changeDetector->detect($localFiles, $expectedState);
        $changes = $diff['files'];

        if ($changes === []) {
            $this->report($progress, 100, 'completed', 'No file changes detected for this target.');

            return [
                'status' => 'success',
                'target' => $target['name'],
                'summary' => $diff['summary'],
                'message' => 'No file changes detected.',
                'engine' => 'incremental',
            ];
        }

        $manifestId = (string) Str::ulid();
        $versionId = (string) Str::ulid();
        $parentManifestId = $remoteState['manifest_id'] ?? $this->stateRepository->latestManifestId($target['name']);
        $objectEntries = [];

        foreach ($changes as $index => $file) {
            $absolutePath = rtrim((string) config('sync.source_path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file['path']);
            $objectEntries[$index] = array_merge($file, $this->objectStore->storeFile($absolutePath));
        }

        $this->report($progress, 45, 'checking-objects', 'Checking which objects the target already has.');
        $missingObjects = $this->transport->checkMissingObjects($target, array_column($objectEntries, 'hash'));
        $missing = array_flip($missingObjects['missing'] ?? []);

        $this->report($progress, 60, 'uploading-objects', 'Uploading only the changed objects that the target is missing.');
        foreach ($objectEntries as $entry) {
            if (! isset($missing[$entry['hash']])) {
                continue;
            }

            $this->transport->uploadObject($target, $entry['hash'], $this->objectStore->path($entry['hash']));
        }

        $manifest = [
            'manifest_id' => $manifestId,
            'version_id' => $versionId,
            'timestamp' => now()->toIso8601String(),
            'source_app' => config('sync.target.source_app_id'),
            'target_name' => $target['name'],
            'parent_manifest_id' => $parentManifestId,
            'summary' => $diff['summary'],
            'expected_target' => $expectedState,
            'files' => array_map(static fn (array $entry) => [
                'path' => $entry['path'],
                'hash' => $entry['hash'],
                'size' => $entry['size'] ?? null,
                'modified_at' => $entry['modified_at'] ?? null,
                'status' => $entry['status'],
            ], $objectEntries),
        ];

        $this->report($progress, 80, 'committing', 'Submitting the signed manifest commit to the target.');
        $response = $this->transport->commit($target, $manifest);

        if (($response['status'] ?? null) !== 'success') {
            throw new RuntimeException($response['message'] ?? 'Incremental sync failed.');
        }

        $version = $this->versionManager->createVersion([
            'version_id' => $versionId,
            'operation' => 'sync',
            'direction' => 'outgoing',
            'status' => 'success',
            'source_app' => config('sync.target.source_app_id'),
            'target_name' => $target['name'],
            'summary' => $diff['summary'],
            'metadata' => [
                'engine' => 'incremental',
                'strategy' => 'local-first',
                'source_side' => 'local',
                'apply_direction' => 'local_to_production',
                'confirmation_state' => 'applied',
                'manifest_id' => $manifestId,
                'parent_manifest_id' => $parentManifestId,
            ],
            'applied_at' => now(),
            'completed_at' => now(),
        ]);

        if ($operationId) {
            $this->operationTracker->attachVersion($operationId, $version->id);
        }

        $manifest['signature'] = $response['manifest_signature'] ?? null;
        $this->manifestRepository->create([
            'manifest_id' => $manifestId,
            'target_name' => $target['name'],
            'direction' => 'outgoing',
            'parent_manifest_id' => $parentManifestId,
            'sync_version_id' => $version->id,
            'signature' => $response['manifest_signature'] ?? null,
            'summary' => $diff['summary'],
            'metadata' => [
                'source_app' => config('sync.target.source_app_id'),
            ],
        ], $manifest['files']);

        $mergedState = $this->stateRepository->merge($target['name'], $manifest['files'], $manifestId);
        $this->versionManager->replaceFiles($version, $this->snapshotFiles($mergedState));
        $this->report($progress, 100, 'completed', 'Incremental local-first sync completed.');

        $this->notificationService->notify('DeployCar incremental sync succeeded', [
            'version_id' => $versionId,
            'target' => $target['name'],
            'summary' => $diff['summary'],
        ]);

        return [
            'status' => 'success',
            'version_id' => $versionId,
            'manifest_id' => $manifestId,
            'target' => $target['name'],
            'summary' => $diff['summary'],
            'receiver' => $response,
            'engine' => 'incremental',
        ];
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

    protected function report(?callable $progress, int $percent, string $stage, string $message): void
    {
        if ($progress) {
            $progress($percent, $stage, $message, []);
        }
    }
}
