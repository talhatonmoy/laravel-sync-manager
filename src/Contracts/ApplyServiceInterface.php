<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface ApplyServiceInterface
{
    public function commit(array $manifest, ?callable $progress = null): array;
}
