<?php

namespace DeployCar\LaravelSyncManager\Tests\Unit;

use DeployCar\LaravelSyncManager\Models\SyncTarget;
use DeployCar\LaravelSyncManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

class SyncTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_is_encrypted_at_rest(): void
    {
        $target = SyncTarget::query()->create([
            'name' => 'production-main',
            'url' => 'https://example.test',
            'api_key' => 'my-secret-key',
        ]);

        $this->assertNotSame('my-secret-key', $target->getRawOriginal('api_key'));
        $this->assertSame('my-secret-key', Crypt::decryptString($target->getRawOriginal('api_key')));
    }

    public function test_api_key_decrypts_transparently_on_read(): void
    {
        $target = SyncTarget::query()->create([
            'name' => 'production-second',
            'url' => 'https://example.test',
            'api_key' => 'visible-key',
        ]);

        // Fresh query to bypass model-cached attribute
        $fresh = SyncTarget::query()->where('name', 'production-second')->first();

        $this->assertSame('visible-key', $fresh->api_key);
    }

    public function test_api_key_is_hidden_from_json_serialization(): void
    {
        $target = SyncTarget::query()->create([
            'name' => 'hidden-prod',
            'url' => 'https://example.test',
            'api_key' => 's3cret!',
            'is_default' => true,
        ]);

        $json = $target->toArray();

        $this->assertArrayNotHasKey('api_key', $json);
        $this->assertSame('hidden-prod', $json['name']);
        $this->assertTrue($json['is_default']);
    }

    public function test_empty_api_key_remains_null(): void
    {
        $target = SyncTarget::query()->create([
            'name' => 'no-key',
            'url' => 'https://example.test',
            'api_key' => null,
        ]);

        $this->assertNull($target->getRawOriginal('api_key'));
    }
}
