<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface LocalBackupServiceInterface
{
    public function backupFiles(string $versionId, array $filePaths): array;

    public function restore(string $backupRoot): void;

    public function restoreForVersion(?string $versionId = null, ?callable $progress = null): array;
}
