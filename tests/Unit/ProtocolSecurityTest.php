<?php

namespace DeployCar\LaravelSyncManager\Tests\Unit;

use DeployCar\LaravelSyncManager\Services\ProtocolSecurity;
use DeployCar\LaravelSyncManager\Tests\TestCase;
use Illuminate\Http\Request;
use RuntimeException;

class ProtocolSecurityTest extends TestCase
{
    private ProtocolSecurity $security;

    protected function setUp(): void
    {
        parent::setUp();
        $this->security = app(ProtocolSecurity::class);
    }

    public function test_signature_verifies_with_correct_headers(): void
    {
        $secret = 'test-secret';
        $method = 'POST';
        $path = 'sync/objects/check';
        $timestamp = $this->security->timestamp();
        $nonce = $this->security->nonce();
        $body = json_encode(['hashes' => ['abc123']]);
        $signature = $this->security->sign($method, $path, $timestamp, $nonce, $body, $secret);

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/sync/objects/check',
            ],
            $body
        );
        $request->headers->set('X-Sync-Timestamp', $timestamp);
        $request->headers->set('X-Sync-Nonce', $nonce);
        $request->headers->set('X-Sync-Signature', $signature);

        $this->security->verifyRequest($request, $secret, $body);
        $this->assertTrue(true);
    }

    public function test_rejects_missing_headers(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('headers are missing');

        $request = new Request();
        $request->headers->set('X-Sync-Key', 'secret');

        $this->security->verifyRequest($request, 'secret', '');
    }

    public function test_rejects_bad_signature(): void
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/sync/commit',
            ],
            'original body'
        );
        $request->headers->set('X-Sync-Timestamp', $this->security->timestamp());
        $request->headers->set('X-Sync-Nonce', $this->security->nonce());
        $request->headers->set('X-Sync-Signature', 'bad-signature-value');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verification failed');

        $this->security->verifyRequest($request, 'test-secret', 'original body');
    }

    public function test_rejects_replayed_nonce(): void
    {
        $secret = 'secret';
        $method = 'GET';
        $path = 'sync/state';
        $timestamp = $this->security->timestamp();
        $nonce = $this->security->nonce();
        $body = '';
        $signature = $this->security->sign($method, $path, $timestamp, $nonce, $body, $secret);

        $buildRequest = fn () => new Request([], [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sync/state',
        ], '');

        // First call must pass
        $first = $buildRequest();
        $first->headers->set('X-Sync-Timestamp', $timestamp);
        $first->headers->set('X-Sync-Nonce', $nonce);
        $first->headers->set('X-Sync-Signature', $signature);
        $this->security->verifyRequest($first, $secret, $body);

        // Second call with same nonce should fail
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nonce has already been used');

        $second = $buildRequest();
        $second->headers->set('X-Sync-Timestamp', $timestamp);
        $second->headers->set('X-Sync-Nonce', $nonce);
        $second->headers->set('X-Sync-Signature', $signature);
        $this->security->verifyRequest($second, $secret, $body);
    }

    public function test_rejects_change_me_in_non_local(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sync/state',
        ], '');
        $request->headers->set('X-Sync-Timestamp', $this->security->timestamp());
        $request->headers->set('X-Sync-Nonce', $this->security->nonce());
        $request->headers->set('X-Sync-Signature', 'some-sig');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('default API key');
        $this->security->verifyRequest($request, 'change-me', '');
    }
}
