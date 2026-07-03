<?php

namespace DeployCar\LaravelSyncManager\Contracts;

use DeployCar\LaravelSyncManager\Models\SyncManifest;

interface ManifestRepositoryInterface
{
    public function create(array $attributes, array $files): SyncManifest;

    public function latestForTarget(string $targetName, string $direction = 'outgoing'): ?SyncManifest;

    public function attachVersion(string $manifestId, int $syncVersionId): void;

    public function filesForManifest(string $manifestId): array;
}
