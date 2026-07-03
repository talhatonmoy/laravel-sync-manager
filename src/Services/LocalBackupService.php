<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\LocalBackupServiceInterface;
use DeployCar\LaravelSyncManager\Models\SyncVersion;
use DeployCar\LaravelSyncManager\Support\PathNormalizer;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

class LocalBackupService implements LocalBackupServiceInterface
{
    public function __construct(
        protected Filesystem $files
    ) {
    }

    public function backupFiles(string $versionId, array $filePaths): array
    {
        $root = rtrim((string) config('sync.storage_root'), DIRECTORY_SEPARATOR).'/local_backups/'.$versionId;
        $manifest = [];

        foreach ($filePaths as $filePath) {
            $relativePath = PathNormalizer::normalize($filePath);
            $absolutePath = $this->sourceRoot().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
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

    public function restore(string $backupRoot): void
    {
        $backupFilesRoot = rtrim($backupRoot, DIRECTORY_SEPARATOR).'/files';

        if (! $this->files->isDirectory($backupFilesRoot)) {
            throw new RuntimeException('Local backup files are missing.');
        }

        foreach ($this->files->allFiles($backupFilesRoot, true) as $file) {
            $relativePath = ltrim(str_replace($backupFilesRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $destinationPath = $this->sourceRoot().DIRECTORY_SEPARATOR.$relativePath;

            $this->files->ensureDirectoryExists(dirname($destinationPath));
            $this->files->copy($file->getPathname(), $destinationPath);
        }
    }

    public function restoreForVersion(?string $versionId = null, ?callable $progress = null): array
    {
        $this->report($progress, 10, 'locating-backup', 'Looking for the local backup snapshot.');

        $version = $versionId
            ? SyncVersion::query()->where('version_id', $versionId)->first()
            : SyncVersion::query()
                ->where('operation', 'pull')
                ->where('status', 'success')
                ->latest('id')
                ->first();

        if (! $version) {
            throw new RuntimeException('No production pull version is available for local restore.');
        }

        $backupRoot = data_get($version->metadata, 'local_backup.backup_root');

        if (! $backupRoot) {
            throw new RuntimeException('The selected version does not contain a local backup.');
        }

        $this->report($progress, 60, 'restoring-local', 'Restoring local files from the saved backup.');
        $this->restore($backupRoot);
        $this->report($progress, 100, 'completed', 'Local backup restore completed.');

        return [
            'status' => 'success',
            'version_id' => $version->version_id,
            'restored_from_backup' => true,
        ];
    }

    protected function sourceRoot(): string
    {
        return rtrim((string) config('sync.source_path', base_path()), DIRECTORY_SEPARATOR);
    }

    protected function report(?callable $progress, int $percent, string $stage, string $message, array $context = []): void
    {
        if ($progress) {
            $progress($percent, $stage, $message, $context);
        }
    }
}
