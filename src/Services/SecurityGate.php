<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\SecurityGateInterface;
use DeployCar\LaravelSyncManager\Exceptions\SecurityViolationException;
use Illuminate\Support\Str;

class SecurityGate implements SecurityGateInterface
{
    public function assertSafe(string $path): void
    {
        $this->assertNotDangerous($path);
        $this->assertAllowedSubtree($path);
    }

    /**
     * Check that the path is not in the dangerous-patterns denylist.
     */
    protected function assertNotDangerous(string $path): void
    {
        if (! config('sync.security.strict_mode', true)) {
            return;
        }

        if (config('sync.security.allow_override', false)) {
            return;
        }

        $dangerousPatterns = config('sync.security.dangerous_patterns', []);
        $normalized = ltrim($path, '/');

        foreach ($dangerousPatterns as $pattern) {
            if (Str::is($pattern, $normalized) || Str::is('*/'.$pattern, $normalized)) {
                throw new SecurityViolationException(
                    $path,
                    "Dangerous file detected. Sync aborted to prevent pushing sensitive data to production."
                );
            }
        }
    }

    /**
     * Check that the path falls under one of the allowed writable subtrees.
     * When the list is empty every path is considered allowed (opt-in mode).
     */
    protected function assertAllowedSubtree(string $path): void
    {
        $subtrees = (array) config('sync.security.writable_subtrees', []);

        if ($subtrees === []) {
            return;
        }

        $normalized = ltrim($path, '/');

        foreach ($subtrees as $subtree) {
            $subtree = rtrim(trim((string) $subtree), '/');

            if ($subtree === '') {
                continue;
            }

            if ($normalized === $subtree || str_starts_with($normalized.'/', $subtree.'/')) {
                return;
            }
        }

        throw new SecurityViolationException(
            $path,
            "File path is outside the allowed writable subtrees."
        );
    }
}
