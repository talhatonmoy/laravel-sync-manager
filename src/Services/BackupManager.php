<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Support\PathNormalizer;
use Illuminate\Filesystem\Filesystem;

class BackupManager
{
    public function __construct(
        protected Filesystem $files
    ) {
    }

    public function backupFiles(string $versionId, array $filePaths): array
    {
        $root = rtrim((string) config('sync.storage_root'), DIRECTORY_SEPARATOR).'/backups/'.$versionId;
        $manifest = [];

        foreach ($filePaths as $filePath) {
            $relativePath = PathNormalizer::normalize($filePath);
            $absolutePath = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
            $backupPath = $root.'/files/'.$relativePath;

            if ($this->files->exists($absolutePath)) {
                $this->files->ensureDirectoryExists(dirname($backupPath));
                $this->files->copy($absolutePath, $backupPath);
                $manifest[] = ['path' => $relativePath, 'status' => 'backed_up'];
            } else {
                $manifest[] = ['path' => $relativePath, 'status' => 'missing'];
            }
        }

        $this->files->ensureDirectoryExists($root);
        $this->files->put($root.'/backup-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'backup_root' => $root,
            'manifest' => $manifest,
        ];
    }
}
