<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface StateRepositoryInterface
{
    public function forTarget(string $targetName): array;

    public function latestManifestId(string $targetName): ?string;

    public function replace(string $targetName, array $state, ?string $manifestId = null): void;

    public function merge(string $targetName, array $changes, ?string $manifestId = null): array;
}
