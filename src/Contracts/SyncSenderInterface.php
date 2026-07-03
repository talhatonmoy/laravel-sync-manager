<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface SyncSenderInterface
{
    public function dryRun(?string $targetName = null, ?callable $progress = null): array;

    public function send(?string $targetName = null, ?callable $progress = null, ?string $operationId = null): array;
}
