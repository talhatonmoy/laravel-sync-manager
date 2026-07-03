<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Models\SyncOperation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OperationTracker implements OperationTrackerInterface
{
    public function start(array $attributes): SyncOperation
    {
        $this->ensureTableExists();

        return SyncOperation::query()->create(array_merge([
            'operation_id' => (string) Str::ulid(),
            'status' => 'queued',
            'progress' => 0,
            'metadata' => [],
            'started_at' => now(),
        ], $attributes));
    }

    public function progress(SyncOperation|string $operation, int $progress, string $stage, string $message, array $metadata = []): SyncOperation
    {
        $this->ensureTableExists();
        $model = $this->resolve($operation);
        $model->fill([
            'status' => 'running',
            'progress' => max(0, min(100, $progress)),
            'stage' => $stage,
            'message' => $message,
            'started_at' => $model->started_at ?: now(),
            'metadata' => array_merge($model->metadata ?? [], $metadata),
        ]);
        $model->save();

        return $model->refresh();
    }

    public function complete(SyncOperation|string $operation, array $result = [], array $attributes = []): SyncOperation
    {
        $this->ensureTableExists();
        $model = $this->resolve($operation);
        $model->fill(array_merge([
            'status' => 'success',
            'progress' => 100,
            'completed_at' => now(),
            'result_payload' => $result,
        ], $attributes));
        $model->save();

        return $model->refresh();
    }

    public function fail(SyncOperation|string $operation, string $message, array $attributes = []): SyncOperation
    {
        $this->ensureTableExists();
        $model = $this->resolve($operation);
        $model->fill(array_merge([
            'status' => 'failed',
            'message' => $message,
            'completed_at' => now(),
        ], $attributes));
        $model->save();

        return $model->refresh();
    }

    public function attachVersion(SyncOperation|string $operation, int $syncVersionId): SyncOperation
    {
        $this->ensureTableExists();
        $model = $this->resolve($operation);
        $model->forceFill(['sync_version_id' => $syncVersionId])->save();

        return $model->refresh();
    }

    public function find(string $operationId): ?SyncOperation
    {
        if (! Schema::hasTable('sync_operations')) {
            return null;
        }

        return SyncOperation::query()->where('operation_id', $operationId)->first();
    }

    public function latest(int $limit = 10): array
    {
        if (! Schema::hasTable('sync_operations')) {
            return [];
        }

        return SyncOperation::query()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    protected function resolve(SyncOperation|string $operation): SyncOperation
    {
        if ($operation instanceof SyncOperation) {
            return $operation;
        }

        return SyncOperation::query()->where('operation_id', $operation)->firstOrFail();
    }

    protected function ensureTableExists(): void
    {
        if (! Schema::hasTable('sync_operations')) {
            throw new \RuntimeException('DeployCar operations are not ready yet. Run php artisan migrate to create the sync_operations table.');
        }
    }
}
