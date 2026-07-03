<?php

namespace DeployCar\LaravelSyncManager\Services;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class LockManager
{
    public function run(string $suffix, Closure $callback): mixed
    {
        if (! config('sync.locking.enabled', true)) {
            return $callback();
        }

        $key = config('sync.locking.key', 'sync-manager:operation').':'.$suffix;
        $ttl = (int) config('sync.locking.ttl', 600);
        $lock = Cache::lock($key, $ttl);

        try {
            if (! $lock->get()) {
                throw new RuntimeException('A sync operation is already running.');
            }

            return $callback();
        } finally {
            rescue(static fn () => $lock->release(), report: false);
        }
    }
}
