<?php

namespace DeployCar\LaravelSyncManager\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ProtocolSecurity
{
    public function nonce(): string
    {
        return (string) \Illuminate\Support\Str::ulid();
    }

    public function timestamp(): string
    {
        return (string) now()->timestamp;
    }

    public function sign(string $method, string $path, string $timestamp, string $nonce, string|bool $bodyOrHash, string $secret, bool $isAlreadyHashed = false): string
    {
        $bodyHash = $isAlreadyHashed ? (string) $bodyOrHash : hash('sha256', (string) $bodyOrHash);

        $canonical = implode("\n", [
            strtoupper($method),
            trim($path),
            $timestamp,
            $nonce,
            $bodyHash,
        ]);

        return hash_hmac('sha256', $canonical, $secret);
    }

    public function verifyRequest(Request $request, string $secret, ?string $body = null): void
    {
        $timestamp = (string) $request->header('X-Sync-Timestamp', '');
        $nonce = (string) $request->header('X-Sync-Nonce', '');
        $signature = (string) $request->header('X-Sync-Signature', '');

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            throw new RuntimeException('Signed sync request headers are missing.');
        }

        // The default key is publicly known; reject it in every non-local
        // environment so staging/preview/CI are protected.
        if (! app()->environment('local') && $secret === 'change-me') {
            throw new RuntimeException('The default API key is not allowed outside local development.');
        }

        $skew = (int) config('sync.protocol.clock_skew_seconds', 300);
        if (abs(now()->timestamp - (int) $timestamp) > $skew) {
            throw new RuntimeException('Signed sync request timestamp is outside the allowed clock skew.');
        }

        $cacheKey = 'sync-manager:nonce:'.$nonce;
        $ttl = (int) config('sync.protocol.nonce_ttl_seconds', 300);
        if (! Cache::add($cacheKey, true, $ttl)) {
            throw new RuntimeException('Signed sync request nonce has already been used.');
        }

        $expected = $this->sign(
            $request->method(),
            $this->getCanonicalPath($request),
            $timestamp,
            $nonce,
            $body ?? $request->getContent(),
            $secret
        );

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Signed sync request verification failed.');
        }
    }

    /**
     * Derive the canonical path from the receiver route prefix onward.
     *
     * Uses strpos (first match) rather than strrpos (last match) to handle
     * paths where the prefix appears multiple times (e.g. "sync/objects/sync").
     */
    protected function getCanonicalPath(Request $request): string
    {
        $prefix = trim((string) config('sync.receiver.route_prefix', 'sync'), '/');
        $path = $request->path();

        if ($prefix === '') {
            return $path;
        }

        $pos = strpos($path, $prefix);
        if ($pos !== false) {
            return substr($path, $pos);
        }

        return $path;
    }
}
