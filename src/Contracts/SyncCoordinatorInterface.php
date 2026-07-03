<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface SyncCoordinatorInterface
{
    public function preview(string $strategy = 'preview', ?string $targetName = null, ?callable $progress = null): array;

    public function applyProductionFirst(?string $targetName = null, ?callable $progress = null, ?string $operationId = null): array;

    public function applyLocalFirst(?string $targetName = null, ?callable $progress = null, ?string $operationId = null): array;
}
