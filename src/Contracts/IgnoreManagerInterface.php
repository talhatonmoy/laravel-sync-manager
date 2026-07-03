<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface IgnoreManagerInterface
{
    public function patterns(): array;

    public function shouldIgnore(string $relativePath): bool;
}
