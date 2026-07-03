<?php

namespace DeployCar\LaravelSyncManager\Tests\Unit;

use DeployCar\LaravelSyncManager\Support\PathNormalizer;
use DeployCar\LaravelSyncManager\Tests\TestCase;

class HarnessSmokeTest extends TestCase
{
    public function test_package_config_is_loaded(): void
    {
        $this->assertSame('test-secret-key', config('sync.receiver.api_key'));
        $this->assertSame($this->storageRoot, config('sync.storage_root'));
    }

    public function test_path_normalizer_is_autoloaded(): void
    {
        $this->assertSame('a/b/c', PathNormalizer::normalize('\\a//b\\c/'));
    }
}
