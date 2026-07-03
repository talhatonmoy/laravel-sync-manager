<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface FileScannerInterface
{
    public function scan(?string $root = null, array $targetState = []): array;

    public function diff(array $sourceFiles, array $targetState): array;
}
