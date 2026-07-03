<?php

namespace DeployCar\LaravelSyncManager\Contracts;

use DeployCar\LaravelSyncManager\Models\SyncOperation;

interface OperationTrackerInterface
{
    public function start(array $attributes): SyncOperation;

    public function progress(SyncOperation|string $operation, int $progress, string $stage, string $message, array $metadata = []): SyncOperation;

    public function complete(SyncOperation|string $operation, array $result = [], array $attributes = []): SyncOperation;

    public function fail(SyncOperation|string $operation, string $message, array $attributes = []): SyncOperation;

    public function attachVersion(SyncOperation|string $operation, int $syncVersionId): SyncOperation;

    public function find(string $operationId): ?SyncOperation;

    public function latest(int $limit = 10): array;
}
