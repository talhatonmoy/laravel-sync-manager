<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface ObjectStoreInterface
{
    public function has(string $hash): bool;

    public function storeFile(string $absolutePath): array;

    public function storeContents(string $contents, ?string $expectedHash = null): array;

    public function path(string $hash): string;

    /**
     * Increment the reference count for a stored object, marking it as
     * currently referenced by at least one active version/manifest.
     */
    public function retain(string $hash): void;

    /**
     * Decrement the reference count for a stored object. Orphaned objects
     * (count reaches 0) are candidates for pruning.
     */
    public function release(string $hash): void;

    /**
     * Delete all object blobs that have a reference count of 0 or are no
     * longer referenced by any active state. Returns the number of blobs
     * removed and bytes freed.
     *
     * @return array{removed: int, freed_bytes: int}
     */
    public function prune(): array;
}
