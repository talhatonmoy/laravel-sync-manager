<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface IncrementalTransportInterface
{
    public function fetchState(array $target): array;

    public function checkMissingObjects(array $target, array $hashes): array;

    public function uploadObject(array $target, string $hash, string $absolutePath): void;

    public function commit(array $target, array $manifest): array;

    public function downloadObject(array $target, string $hash): string;
}
