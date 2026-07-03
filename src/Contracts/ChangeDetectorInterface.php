<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface ChangeDetectorInterface
{
    public function detect(array $sourceFiles, array $targetState): array;

    public function preview(array $localFiles, array $remoteState): array;
}
