<?php

namespace DeployCar\LaravelSyncManager\Tests\Unit;

use DeployCar\LaravelSyncManager\Services\IncrementalTransport;
use DeployCar\LaravelSyncManager\Services\ProtocolSecurity;
use DeployCar\LaravelSyncManager\Tests\TestCase;
use Illuminate\Http\Request;
use ReflectionMethod;

class Phase3UnitTest extends TestCase
{
    // ---------- L5: canonical path uses first-match strpos ----------

    public function test_canonical_path_first_match_not_last(): void
    {
        $security = app(ProtocolSecurity::class);
        $ref = new ReflectionMethod($security, 'getCanonicalPath');

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/sync/objects/sync',
        ]);

        $canonical = $ref->invoke($security, $request);

        $this->assertSame('sync/objects/sync', $canonical);
    }

    public function test_canonical_path_without_prefix(): void
    {
        config()->set('sync.receiver.route_prefix', '');

        $security = app(ProtocolSecurity::class);
        $ref = new ReflectionMethod($security, 'getCanonicalPath');

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/state',
        ]);

        $this->assertSame('state', $ref->invoke($security, $request));
    }

    // ---------- H3b: SSRF block ----------

    public function test_ssrf_blocks_localhost(): void
    {
        $transport = app(IncrementalTransport::class);
        $ref = new ReflectionMethod($transport, 'assertTargetUrlSafe');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked');

        $ref->invoke($transport, 'http://localhost/internal');
    }

    public function test_ssrf_blocks_127_ip(): void
    {
        $transport = app(IncrementalTransport::class);
        $ref = new ReflectionMethod($transport, 'assertTargetUrlSafe');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked');

        $ref->invoke($transport, 'http://127.0.0.1/sync/state');
    }

    public function test_ssrf_blocks_private_10_network(): void
    {
        $transport = app(IncrementalTransport::class);
        $ref = new ReflectionMethod($transport, 'assertTargetUrlSafe');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked');

        $ref->invoke($transport, 'http://10.0.0.5/commit');
    }

    public function test_ssrf_blocks_private_192_168(): void
    {
        $transport = app(IncrementalTransport::class);
        $ref = new ReflectionMethod($transport, 'assertTargetUrlSafe');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked');

        $ref->invoke($transport, 'http://192.168.1.1/state');
    }

    public function test_ssrf_blocks_link_local(): void
    {
        $transport = app(IncrementalTransport::class);
        $ref = new ReflectionMethod($transport, 'assertTargetUrlSafe');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('blocked');

        $ref->invoke($transport, 'http://169.254.169.254/latest/meta-data/');
    }

    public function test_ssrf_allows_public_https(): void
    {
        $transport = app(IncrementalTransport::class);
        $ref = new ReflectionMethod($transport, 'assertTargetUrlSafe');

        $ref->invoke($transport, 'https://api.example.com/sync/state');
        $this->assertTrue(true);
    }

    public function test_ssrf_can_be_disabled_via_config(): void
    {
        config()->set('sync.advanced.block_private_ips', false);

        $transport = app(IncrementalTransport::class);
        $ref = new ReflectionMethod($transport, 'assertTargetUrlSafe');

        $ref->invoke($transport, 'http://localhost/internal');
        $this->assertTrue(true);
    }
}
