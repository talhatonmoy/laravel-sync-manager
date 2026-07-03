<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Models\SyncFileObject;
use Illuminate\Filesystem\Filesystem;

class ObjectStore implements ObjectStoreInterface
{
    public function __construct(
        protected Filesystem $files
    ) {
    }

    public function has(string $hash): bool
    {
        return SyncFileObject::query()->where('hash', $hash)->exists() && $this->files->exists($this->path($hash));
    }

    public function storeFile(string $absolutePath): array
    {
        if (! $this->files->exists($absolutePath)) {
            throw new \RuntimeException("Source file [{$absolutePath}] does not exist.");
        }

        $hash = hash_file('sha256', $absolutePath);
        $path = $this->path($hash);
        $this->files->ensureDirectoryExists(dirname($path));

        if (! $this->files->exists($path)) {
            $this->files->copy($absolutePath, $path);
        }

        $record = SyncFileObject::query()->firstOrCreate(
            ['hash' => $hash],
            [
                'size' => $this->files->size($absolutePath),
                'storage_path' => $path,
                'reference_count' => 0,
            ]
        );

        if ($record->wasRecentlyCreated) {
            $this->retain($hash);
        }

        return [
            'hash' => $hash,
            'size' => $record->size,
            'storage_path' => $path,
        ];
    }

    public function storeContents(string $contents, ?string $expectedHash = null): array
    {
        $hash = hash('sha256', $contents);

        if ($expectedHash !== null && ! hash_equals($expectedHash, $hash)) {
            throw new \RuntimeException('Blob hash verification failed.');
        }

        $path = $this->path($hash);
        $this->files->ensureDirectoryExists(dirname($path));

        if (! $this->files->exists($path)) {
            $this->files->put($path, $contents);
        }

        $record = SyncFileObject::query()->firstOrCreate(
            ['hash' => $hash],
            [
                'size' => strlen($contents),
                'storage_path' => $path,
                'reference_count' => 0,
            ]
        );

        if ($record->wasRecentlyCreated) {
            $this->retain($hash);
        }

        return [
            'hash' => $hash,
            'size' => $record->size,
            'storage_path' => $path,
        ];
    }

    public function path(string $hash): string
    {
        if (! ctype_xdigit($hash) || strlen($hash) !== 64) {
            throw new \RuntimeException("Malformed object hash [{$hash}].");
        }

        $root = rtrim((string) config('sync.storage_root'), DIRECTORY_SEPARATOR);
        $directory = trim((string) config('sync.objects.directory', 'objects'), DIRECTORY_SEPARATOR);

        return $root.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.substr($hash, 0, 2).DIRECTORY_SEPARATOR.substr($hash, 2, 2).DIRECTORY_SEPARATOR.$hash.'.blob';
    }

    public function retain(string $hash): void
    {
        SyncFileObject::query()->where('hash', $hash)->increment('reference_count');
    }

    public function release(string $hash): void
    {
        SyncFileObject::query()->where('hash', $hash)->where('reference_count', '>', 0)->decrement('reference_count');
    }

    public function prune(): array
    {
        $orphans = SyncFileObject::query()
            ->where('reference_count', '<=', 0)
            ->get();

        $removed = 0;
        $freed = 0;

        foreach ($orphans as $orphan) {
            $blobPath = $this->path($orphan->hash);

            if ($this->files->exists($blobPath)) {
                $freed += $this->files->size($blobPath);
                $this->files->delete($blobPath);
            }

            $orphan->delete();
            $removed++;
        }

        return [
            'removed' => $removed,
            'freed_bytes' => $freed,
        ];
    }
}
