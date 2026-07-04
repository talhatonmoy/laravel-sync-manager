<?php

namespace DeployCar\LaravelSyncManager\Tests\Unit;

use DeployCar\LaravelSyncManager\Contracts\FileScannerInterface;
use DeployCar\LaravelSyncManager\Models\SyncLocalCache;
use DeployCar\LaravelSyncManager\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScanCacheTest extends TestCase
{
    use RefreshDatabase;

    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = $this->sourceRoot.'/scan-cache-test';
        (new Filesystem())->ensureDirectoryExists($this->testDir);
    }

    public function test_first_scan_populates_cache(): void
    {
        file_put_contents($this->testDir.'/hello.txt', 'hello world');

        $scanner = app(FileScannerInterface::class);
        $result = $scanner->scan($this->testDir);

        $this->assertCount(1, $result);
        $this->assertSame('hello.txt', $result[0]['path']);

        $cached = SyncLocalCache::query()->where('path', 'hello.txt')->first();
        $this->assertNotNull($cached);
        $this->assertSame($result[0]['hash'], $cached->hash);
    }

    public function test_second_scan_reuses_cache_on_unchanged_file(): void
    {
        file_put_contents($this->testDir.'/cache-me.txt', 'stable content');

        $scanner = app(FileScannerInterface::class);
        $first = $scanner->scan($this->testDir);
        $firstHash = $first[0]['hash'];
        $cacheCount = SyncLocalCache::query()->count();

        // Second scan with same mtime/size
        $second = $scanner->scan($this->testDir);

        $this->assertSame($firstHash, $second[0]['hash']);
        // No new cache rows created
        $this->assertSame($cacheCount, SyncLocalCache::query()->count());
    }

    public function test_modified_file_updates_cache(): void
    {
        $file = $this->testDir.'/change.txt';
        file_put_contents($file, 'original');

        $scanner = app(FileScannerInterface::class);
        $original = $scanner->scan($this->testDir);

        // Modify after a small delay so mtime changes
        sleep(1);
        file_put_contents($file, 'modified content');

        $modified = $scanner->scan($this->testDir);

        $this->assertNotSame($original[0]['hash'], $modified[0]['hash']);

        $cached = SyncLocalCache::query()->where('path', 'change.txt')->first();
        $this->assertSame($modified[0]['hash'], $cached->hash);
    }
}
