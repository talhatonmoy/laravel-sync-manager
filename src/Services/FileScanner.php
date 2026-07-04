<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\FileScannerInterface;
use DeployCar\LaravelSyncManager\Contracts\IgnoreManagerInterface;
use DeployCar\LaravelSyncManager\Contracts\SecurityGateInterface;
use DeployCar\LaravelSyncManager\Models\SyncLocalCache;
use DeployCar\LaravelSyncManager\Support\PathNormalizer;
use Illuminate\Filesystem\Filesystem;

class FileScanner implements FileScannerInterface
{
    public function __construct(
        protected Filesystem $files,
        protected IgnoreManagerInterface $ignoreManager,
        protected SecurityGateInterface $securityGate
    ) {
    }

    public function scan(?string $root = null, array $targetState = []): array
    {
        $root = $root ?: (string) config('sync.source_path');
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        $entries = [];

        foreach ($this->files->allFiles($root, true) as $file) {
            $absolutePath = $file->getPathname();
            $relativePath = PathNormalizer::normalize(ltrim(substr($absolutePath, strlen($root)), DIRECTORY_SEPARATOR));

            if ($this->ignoreManager->shouldIgnore($relativePath)) {
                continue;
            }

            $this->securityGate->assertSafe($relativePath);

            $currentMtime = $file->getMTime();
            $currentSize = $file->getSize();

            // Scan-cache: reuse hash when mtime and size haven't changed.
            $hash = $this->resolveHash($relativePath, $currentMtime, $currentSize, $absolutePath);

            $target = $targetState[$relativePath] ?? null;
            $status = $target === null ? 'add' : (($target['hash'] ?? null) === $hash ? 'unchanged' : 'modify');

            $entries[] = [
                'path' => $relativePath,
                'hash' => $hash,
                'size' => $currentSize,
                'modified_at' => date(DATE_ATOM, $currentMtime),
                'status' => $status,
            ];
        }

        usort($entries, static fn (array $left, array $right) => strcmp($left['path'], $right['path']));

        return $entries;
    }

    /**
     * Return the SHA-256 hash for a file, using the local scan-cache
     * to avoid re-hashing files whose mtime and size haven't changed.
     */
    protected function resolveHash(string $path, int $mtime, int $size, string $absolutePath): string
    {
        if (! (\Illuminate\Support\Facades\Schema::hasTable('sync_local_cache'))) {
            return hash_file('sha256', $absolutePath);
        }

        $cached = SyncLocalCache::query()->where('path', $path)->first();

        if ($cached && (int) $cached->mtime === $mtime && (int) $cached->size === $size) {
            return $cached->hash;
        }

        $hash = hash_file('sha256', $absolutePath);

        SyncLocalCache::query()->updateOrCreate(
            ['path' => $path],
            ['mtime' => $mtime, 'size' => $size, 'hash' => $hash]
        );

        return $hash;
    }

    public function diff(array $sourceFiles, array $targetState): array
    {
        $sourceMap = [];

        foreach ($sourceFiles as $file) {
            $sourceMap[$file['path']] = $file;
        }

        $deleteLater = [];

        foreach ($targetState as $path => $file) {
            if (! isset($sourceMap[$path])) {
                $deleteLater[] = [
                    'path' => $path,
                    'hash' => $file['hash'] ?? null,
                    'status' => 'delete_later',
                ];
            }
        }

        usort($deleteLater, static fn (array $left, array $right) => strcmp($left['path'], $right['path']));

        return [
            'files' => $sourceFiles,
            'delete_later' => $deleteLater,
            'summary' => [
                'add' => count(array_filter($sourceFiles, static fn (array $file) => $file['status'] === 'add')),
                'modify' => count(array_filter($sourceFiles, static fn (array $file) => $file['status'] === 'modify')),
                'unchanged' => count(array_filter($sourceFiles, static fn (array $file) => $file['status'] === 'unchanged')),
                'delete_later' => count($deleteLater),
            ],
        ];
    }
}
