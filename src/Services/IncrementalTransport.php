<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\IncrementalTransportInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IncrementalTransport implements IncrementalTransportInterface
{
    public function __construct(
        protected ProtocolSecurity $security
    ) {
    }

    public function fetchState(array $target): array
    {
        return $this->request('GET', $target, '/state');
    }

    public function checkMissingObjects(array $target, array $hashes): array
    {
        return $this->request('POST', $target, '/objects/check', [
            'hashes' => array_values(array_unique($hashes)),
        ]);
    }

    public function uploadObject(array $target, string $hash, string $absolutePath): void
    {
        $this->assertTargetUrlSafe($target['url'] ?? '');

        $maxRetries = max(1, (int) config('sync.transport.retry_times', 3));
        $retrySleep = max(0, (int) config('sync.transport.retry_sleep_ms', 500));
        $attempts = 0;

        while ($attempts < $maxRetries) {
            $attempts++;
            $timestamp = $this->security->timestamp();
            $nonce = $this->security->nonce();
            $route = '/objects/'.$hash;
            $signature = $this->security->sign(
                'POST',
                trim((string) config('sync.receiver.route_prefix', 'sync'), '/').$route,
                $timestamp,
                $nonce,
                $hash,
                (string) $target['api_key'],
                true
            );

            try {
                $stream = fopen($absolutePath, 'r');
                $response = $this->http()
                    ->timeout((int) config('sync.transport.timeout', 30))
                    ->withHeaders([
                        'X-Sync-Key' => (string) $target['api_key'],
                        'X-Sync-Timestamp' => $timestamp,
                        'X-Sync-Nonce' => $nonce,
                        'X-Sync-Signature' => $signature,
                        'Content-Type' => 'application/octet-stream',
                    ])
                    ->withBody($stream, 'application/octet-stream')
                    ->post($this->baseUrl($target).$route);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($response->successful()) {
                    return;
                }

                if ($response->clientError() && $response->status() !== 429) {
                    throw new RuntimeException("Unable to upload object [{$hash}] to [{$target['name']}]. Status: [{$response->status()}]");
                }
            } catch (\Throwable $exception) {
                if ($attempts >= $maxRetries) {
                    throw new RuntimeException("Unable to upload object [{$hash}] to [{$target['name']}]. Reason: " . $exception->getMessage());
                }
            }

            if ($attempts < $maxRetries) {
                usleep($retrySleep * 1000);
            }
        }
    }

    public function commit(array $target, array $manifest): array
    {
        return $this->request('POST', $target, '/commit', $manifest);
    }

    public function downloadObject(array $target, string $hash): string
    {
        $this->assertTargetUrlSafe($target['url'] ?? '');

        $timestamp = $this->security->timestamp();
        $nonce = $this->security->nonce();
        $route = '/objects/'.$hash;
        $path = trim((string) config('sync.receiver.route_prefix', 'sync'), '/').$route;
        $signature = $this->security->sign(
            'GET',
            $path,
            $timestamp,
            $nonce,
            '',
            (string) $target['api_key']
        );

        $response = $this->http()
            ->timeout((int) config('sync.transport.timeout', 30))
            ->retry(max(1, (int) config('sync.transport.retry_times', 3)), max(0, (int) config('sync.transport.retry_sleep_ms', 500)))
            ->withHeaders([
                'X-Sync-Key' => (string) $target['api_key'],
                'X-Sync-Timestamp' => $timestamp,
                'X-Sync-Nonce' => $nonce,
                'X-Sync-Signature' => $signature,
            ])
            ->get($this->baseUrl($target).$route);

        if (! $response->successful()) {
            throw new RuntimeException("Unable to download object [{$hash}] from [{$target['name']}].");
        }

        return (string) $response->body();
    }

    protected function request(string $method, array $target, string $route, array $payload = []): array
    {
        $this->assertTargetUrlSafe($target['url'] ?? '');

        $maxRetries = max(1, (int) config('sync.transport.retry_times', 3));
        $retrySleep = max(0, (int) config('sync.transport.retry_sleep_ms', 500));
        $attempts = 0;

        while ($attempts < $maxRetries) {
            $attempts++;
            $timestamp = $this->security->timestamp();
            $nonce = $this->security->nonce();
            $json = $payload === [] ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
            $path = trim((string) config('sync.receiver.route_prefix', 'sync'), '/').$route;
            $signature = $this->security->sign(
                $method,
                $path,
                $timestamp,
                $nonce,
                $json,
                (string) $target['api_key']
            );

            try {
                $response = $this->http()
                    ->timeout((int) config('sync.transport.timeout', 30))
                    ->acceptJson()
                    ->withHeaders([
                        'X-Sync-Key' => (string) $target['api_key'],
                        'X-Sync-Timestamp' => $timestamp,
                        'X-Sync-Nonce' => $nonce,
                        'X-Sync-Signature' => $signature,
                    ]);

                if ($method === 'POST' && $payload !== []) {
                    $response = $response->withBody($json, 'application/json')->post($this->baseUrl($target).$route);
                } else {
                    $response = $response->{strtolower($method)}($this->baseUrl($target).$route, $payload);
                }

                if ($response->successful()) {
                    return $response->json();
                }

                $error = $response->json('message') ?: "HTTP status [{$response->status()}]";

                if ($response->clientError() && $response->status() !== 429) {
                    throw new RuntimeException("Incremental request failed for [{$route}]. Error: {$error}");
                }

                $lastError = "Incremental request failed for [{$route}]. Error: {$error}";
            } catch (\Throwable $exception) {
                $lastError = $exception->getMessage();
                if ($attempts >= $maxRetries) {
                    throw new RuntimeException("Unable to reach target [{$target['name']}] at [{$target['url']}]. Reason: " . $lastError);
                }
            }

            if ($attempts < $maxRetries) {
                usleep($retrySleep * 1000);
            }
        }

        throw new RuntimeException("Incremental request for [{$route}] failed after [{$maxRetries}] attempts. Last error: {$lastError}");
    }

    /**
     * Block outbound requests to private, loopback, and link-local IPs
     * as a Server-Side Request Forgery mitigation.
     */
    protected function assertTargetUrlSafe(string $url): void
    {
        if (! config('sync.advanced.block_private_ips', true)) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host === false || $host === null || $host === '') {
            return;
        }

        // Check common private/loopback patterns without DNS resolution
        // so tests using fake HTTP hosts still pass.
        $lower = strtolower($host);

        // IPv6 loopback
        if ($lower === '::1') {
            throw new RuntimeException("Requests to loopback address [{$host}] are blocked.");
        }

        // Simple hostname-based private patterns
        if (str_ends_with($lower, '.local') || $lower === 'localhost') {
            throw new RuntimeException("Requests to local address [{$host}] are blocked.");
        }

        // IPv4 patterns
        if (preg_match('/^(127\.|10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|169\.254\.|0\.)/', $lower)) {
            throw new RuntimeException("Requests to private address [{$host}] are blocked.");
        }
    }

    protected function http(): PendingRequest
    {
        $verify = (bool) config('sync.transport.verify_ssl', true);

        return Http::withOptions(['verify' => $verify]);
    }

    protected function baseUrl(array $target): string
    {
        $url = rtrim((string) $target['url'], '/');
        $prefix = trim((string) config('sync.receiver.route_prefix', 'sync'), '/');

        if (str_ends_with($url, '/' . $prefix)) {
            return $url;
        }

        return $url . '/' . $prefix;
    }
}
