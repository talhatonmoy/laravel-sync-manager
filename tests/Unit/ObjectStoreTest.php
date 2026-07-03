<?php

namespace DeployCar\LaravelSyncManager\Tests\Unit;

use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Tests\TestCase;

class ObjectStoreTest extends TestCase
{
    public function test_path_accepts_valid_sha256_hash(): void
    {
        $hash = hash('sha256', 'test content');

        $path = app(ObjectStoreInterface::class)->path($hash);

        $this->assertStringContainsString($hash, $path);
    }

    public function test_path_rejects_malformed_hash(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Malformed object hash');

        app(ObjectStoreInterface::class)->path('not-a-hex-string');
    }

    public function test_path_rejects_wrong_length_hash(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Malformed object hash');

        app(ObjectStoreInterface::class)->path('abc123');
    }
}
