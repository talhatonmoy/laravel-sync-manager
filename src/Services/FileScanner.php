<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\FileScannerInterface;
use DeployCar\LaravelSyncManager\Contracts\IgnoreManagerInterface;
use DeployCar\LaravelSyncManager\Contracts\SecurityGateInterface;
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

            $hash = hash_file('sha256', $absolutePath);
            $target = $targetState[$relativePath] ?? null;
            $status = $target === null ? 'add' : (($target['hash'] ?? null) === $hash ? 'unchanged' : 'modify');

            $entries[] = [
                'path' => $relativePath,
                'hash' => $hash,
                'size' => $file->getSize(),
                'modified_at' => date(DATE_ATOM, $file->getMTime()),
                'status' => $status,
            ];
        }

        usort($entries, static fn (array $left, array $right) => strcmp($left['path'], $right['path']));

        return $entries;
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
