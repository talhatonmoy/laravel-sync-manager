<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Support\PathNormalizer;
use RuntimeException;

class PathSecurity
{
    public function assertSafe(string $relativePath): string
    {
        $normalized = PathNormalizer::normalize($relativePath);

        if ($normalized === '' || $normalized === '.' || str_contains($normalized, "\0")) {
            throw new RuntimeException('Invalid empty file path.');
        }

        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            throw new RuntimeException("Absolute paths are not allowed [{$relativePath}].");
        }

        $segments = explode('/', $normalized);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                throw new RuntimeException("Parent traversal is not allowed [{$relativePath}].");
            }
        }

        return $normalized;
    }
}
