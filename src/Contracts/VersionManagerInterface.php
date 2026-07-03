<?php

namespace DeployCar\LaravelSyncManager\Contracts;

use DeployCar\LaravelSyncManager\Models\SyncVersion;

interface VersionManagerInterface
{
    public function createVersion(array $attributes): SyncVersion;

    public function updateStatus(SyncVersion $version, string $status, array $attributes = []): SyncVersion;

    public function replaceFiles(SyncVersion $version, array $files): void;

    public function log(?SyncVersion $version, string $level, string $stage, string $message, array $context = []): void;

    public function latestSuccessful(?string $operation = null): ?SyncVersion;

    public function currentState(): array;
}
