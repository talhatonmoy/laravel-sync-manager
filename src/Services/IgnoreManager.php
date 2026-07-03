<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\IgnoreManagerInterface;
use DeployCar\LaravelSyncManager\Support\PathNormalizer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class IgnoreManager implements IgnoreManagerInterface
{
    protected ?array $patterns = null;

    public function __construct(
        protected Filesystem $files
    ) {
    }

    public function patterns(): array
    {
        if ($this->patterns !== null) {
            return $this->patterns;
        }

        $defaults = config('sync.ignore.defaults', []);
        $ignoreFile = rtrim((string) config('sync.source_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .config('sync.ignore.file_name', '.syncignore');

        $custom = [];

        if ($this->files->exists($ignoreFile)) {
            $custom = collect(preg_split('/\r\n|\r|\n/', $this->files->get($ignoreFile)) ?: [])
                ->map(static fn (string $line) => trim($line))
                ->filter(static fn (string $line) => $line !== '' && ! str_starts_with($line, '#'))
                ->values()
                ->all();
        }

        return $this->patterns = array_values(array_unique(array_merge($defaults, $custom)));
    }

    public function shouldIgnore(string $relativePath): bool
    {
        $path = PathNormalizer::normalize($relativePath);

        foreach ($this->patterns() as $pattern) {
            $pattern = trim((string) $pattern);

            if ($pattern === '') {
                continue;
            }

            $isDirectoryPattern = str_ends_with($pattern, '/');
            $pattern = PathNormalizer::normalize($pattern);

            if ($isDirectoryPattern) {
                $dir = rtrim($pattern, '/');

                if ($path === $dir || str_starts_with($path.'/', $dir.'/')) {
                    return true;
                }
            }

            if ($path === $pattern || basename($path) === $pattern) {
                return true;
            }

            if (Str::is($pattern, $path) || Str::is($pattern, basename($path))) {
                return true;
            }
        }

        return false;
    }
}
