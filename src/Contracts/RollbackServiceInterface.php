<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface RollbackServiceInterface
{
    public function rollbackTo(?string $versionId = null, ?callable $progress = null, ?string $operationId = null): array;

    public function undoLastSync(?callable $progress = null, ?string $operationId = null): array;
}
