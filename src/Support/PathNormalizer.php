<?php

namespace DeployCar\LaravelSyncManager\Support;

class PathNormalizer
{
    public static function normalize(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;

        return trim($normalized, '/');
    }
}
