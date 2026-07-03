<?php

namespace DeployCar\LaravelSyncManager\Tests\Feature;

use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Models\SyncFileObject;
use DeployCar\LaravelSyncManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ObjectStoreFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_files_creates_blob_and_record(): void
    {
        $store = app(ObjectStoreInterface::class);
        $content = 'hello-object-store';
        $hash = hash('sha256', $content);
        $result = $store->storeContents($content, $hash);

        $this->assertSame($hash, $result['hash']);
        $this->assertTrue($store->has($hash));
        $this->assertFileExists($store->path($hash));
    }

    public function test_store_files_rejects_hash_mismatch(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Blob hash verification failed');

        $store = app(ObjectStoreInterface::class);
        $store->storeContents('real content', hash('sha256', 'different content'));
    }

    // ---------- GC: retain / release / prune ----------

    public function test_new_object_starts_with_one_reference(): void
    {
        $store = app(ObjectStoreInterface::class);
        $hash = hash('sha256', 'gc-test-content');
        $store->storeContents('gc-test-content', $hash);

        $record = SyncFileObject::query()->where('hash', $hash)->first();
        $this->assertNotNull($record);
        $this->assertSame(1, $record->reference_count);
    }

    public function test_existing_object_does_not_increment_again(): void
    {
        $store = app(ObjectStoreInterface::class);
        $hash = hash('sha256', 'same-content');
        $store->storeContents('same-content', $hash);
        $store->storeContents('same-content', $hash);

        $record = SyncFileObject::query()->where('hash', $hash)->first();
        $this->assertSame(1, $record->reference_count);
    }

    public function test_retain_increments_reference_count(): void
    {
        $store = app(ObjectStoreInterface::class);
        $hash = hash('sha256', 'retain-test');
        $store->storeContents('retain-test', $hash);

        $store->retain($hash);

        $record = SyncFileObject::query()->where('hash', $hash)->first();
        $this->assertSame(2, $record->reference_count);
    }

    public function test_release_decrements_reference_count(): void
    {
        $store = app(ObjectStoreInterface::class);
        $hash = hash('sha256', 'release-test');
        $store->storeContents('release-test', $hash);

        $store->retain($hash); // ref = 2
        $store->release($hash); // ref = 1
        $store->release($hash); // ref = 0

        $record = SyncFileObject::query()->where('hash', $hash)->first();
        $this->assertSame(0, $record->reference_count);
    }

    public function test_release_never_goes_below_zero(): void
    {
        $store = app(ObjectStoreInterface::class);
        $hash = hash('sha256', 'floor-test');
        $store->storeContents('floor-test', $hash);

        // ref = 1 from creation
        $store->release($hash); // 0
        $store->release($hash); // would be -1 if not guarded, should stay 0

        $record = SyncFileObject::query()->where('hash', $hash)->first();
        $this->assertSame(0, $record->reference_count);
    }

    public function test_prune_removes_orphaned_records_and_blobs(): void
    {
        $store = app(ObjectStoreInterface::class);

        // Keep this one alive
        $keepHash = hash('sha256', 'keep-me');
        $store->storeContents('keep-me', $keepHash);

        // Create and fully release this one so it becomes orphaned
        $orphanHash = hash('sha256', 'orphan-me');
        $store->storeContents('orphan-me', $orphanHash);
        $orphanPath = $store->path($orphanHash);
        $store->release($orphanHash); // ref goes from 1 to 0

        $this->assertFileExists($orphanPath);

        $result = $store->prune();

        $this->assertSame(1, $result['removed']);
        $this->assertGreaterThan(0, $result['freed_bytes']);
        $this->assertFileDoesNotExist($orphanPath);
        $this->assertFalse($store->has($orphanHash));

        // The retained object should still exist
        $this->assertTrue($store->has($keepHash));
    }

    public function test_prune_skips_active_objects(): void
    {
        $store = app(ObjectStoreInterface::class);

        $hash = hash('sha256', 'active');
        $store->storeContents('active', $hash);
        // ref = 1, should not be pruned

        $result = $store->prune();

        $this->assertSame(0, $result['removed']);
        $this->assertTrue($store->has($hash));
    }
}
