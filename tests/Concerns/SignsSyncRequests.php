<?php

namespace DeployCar\LaravelSyncManager\Tests\Concerns;

use DeployCar\LaravelSyncManager\Services\ProtocolSecurity;
use Illuminate\Testing\TestResponse;

/**
 * Helpers for issuing HMAC-signed receiver requests in feature tests.
 *
 * Mirrors the signing performed by IncrementalTransport so tests exercise the
 * real ProtocolSecurity verification path rather than mocking it.
 */
trait SignsSyncRequests
{
    protected function syncKey(): string
    {
        return (string) config('sync.receiver.api_key');
    }

    /**
     * The canonical path used for signing. Routes are passed already prefixed
     * (e.g. "sync/objects/check"), which is exactly what the receiver's
     * ProtocolSecurity::getCanonicalPath() derives from the request path.
     */
    protected function canonicalPath(string $route): string
    {
        return ltrim($route, '/');
    }

    /**
     * @return array{0: string, 1: string, 2: string} [timestamp, nonce, signature]
     */
    protected function signedHeaders(string $method, string $route, string $body): array
    {
        $security = app(ProtocolSecurity::class);
        $timestamp = $security->timestamp();
        $nonce = $security->nonce();
        $signature = $security->sign(
            $method,
            $this->canonicalPath($route),
            $timestamp,
            $nonce,
            $body,
            $this->syncKey()
        );

        return [$timestamp, $nonce, $signature];
    }

    /**
     * Send a signed JSON POST via Laravel's ->json() helper.
     * Signed with default json_encode (matching ->json() internal encoding).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function signedJsonPost(string $route, array $payload): TestResponse
    {
        $json = json_encode($payload);
        [$timestamp, $nonce, $signature] = $this->signedHeaders('POST', $route, $json);

        return $this
            ->withHeaders([
                'X-Sync-Key' => $this->syncKey(),
                'X-Sync-Timestamp' => $timestamp,
                'X-Sync-Nonce' => $nonce,
                'X-Sync-Signature' => $signature,
            ])
            ->json('POST', '/'.ltrim($route, '/'), $payload);
    }

    protected function signedBinaryPost(string $route, string $body): TestResponse
    {
        [$timestamp, $nonce, $signature] = $this->signedHeaders('POST', $route, $body);

        return $this->call('POST', '/'.ltrim($route, '/'), [], [], [], [
            'HTTP_X_SYNC_KEY' => $this->syncKey(),
            'HTTP_X_SYNC_TIMESTAMP' => $timestamp,
            'HTTP_X_SYNC_NONCE' => $nonce,
            'HTTP_X_SYNC_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/octet-stream',
        ], $body);
    }

    protected function signedGet(string $route): TestResponse
    {
        [$timestamp, $nonce, $signature] = $this->signedHeaders('GET', $route, '');

        return $this
            ->withHeaders([
                'X-Sync-Key' => $this->syncKey(),
                'X-Sync-Timestamp' => $timestamp,
                'X-Sync-Nonce' => $nonce,
                'X-Sync-Signature' => $signature,
            ])
            ->getJson('/'.ltrim($route, '/'));
    }
}
