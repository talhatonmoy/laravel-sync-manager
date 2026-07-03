<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface ProductionPullServiceInterface
{
    public function preview(?string $targetName = null, string $strategy = 'production-first', ?callable $progress = null): array;

    public function pull(?string $targetName = null, ?callable $progress = null, ?string $operationId = null): array;
}
