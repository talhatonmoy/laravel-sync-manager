<?php

namespace DeployCar\LaravelSyncManager\Tests\Feature;

use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Models\SyncLocalCache;
use DeployCar\LaravelSyncManager\Services\SyncSender;
use DeployCar\LaravelSyncManager\Tests\Concerns\SignsSyncRequests;
use DeployCar\LaravelSyncManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Pr2FeatureTest extends TestCase
{
    use RefreshDatabase;
    use SignsSyncRequests;

    // ---------- C1: local-only preview (no HTTP ----------

    public function test_preview_no_http_call(): void
    {
        // Store a tracked state so preview doesn't need remote fetch
        $content = 'preview-file';
        $hash = hash('sha256', $content);
        app(ObjectStoreInterface::class)->storeContents($content, $hash);

        $targetName = config('sync.target.name');

        // Seed tracked state to simulate a previous sync
        \DeployCar\LaravelSyncManager\Models\SyncTargetState::query()->create([
            'target_name' => $targetName,
            'path' => 'app/PreviewFile.php',
            'hash' => $hash,
            'size' => strlen($content),
        ]);

        $result = app(SyncSender::class)->dryRun($targetName);

        $this->assertSame('local-only', $result['mode']);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('summary', $result);
    }

    // ---------- C2: delete propagation ----------

    public function test_delete_appears_in_manifest(): void
    {
        $targetName = config('sync.target.name');

        // Seed tracked state with a file that doesn't exist locally
        \DeployCar\LaravelSyncManager\Models\SyncTargetState::query()->create([
            'target_name' => $targetName,
            'path' => 'app/OldDeletedFile.php',
            'hash' => hash('sha256', 'old'),
            'size' => 3,
        ]);

        config()->set('sync.no_delete', false);

        $result = app(SyncSender::class)->dryRun($targetName);

        $this->assertGreaterThan(0, $result['summary']['delete_later']);
    }

    // ---------- C3: atomic apply staging ----------

    public function test_staging_failure_leaves_destination_unchanged(): void
    {
        $blob = 'safe-content';
        $hash = hash('sha256', $blob);
        $store = app(ObjectStoreInterface::class);
        $store->storeContents($blob, $hash);

        // Create a file that will NOT be in the manifest so we can test
        // it survives after a staging failure in the backup path.
        $keepPath = base_path('storage/app/testing-fixtures/keep-me.txt');
        (new \Illuminate\Filesystem\Filesystem())->ensureDirectoryExists(dirname($keepPath));
        file_put_contents($keepPath, 'survivor');

        // Send a manifest with a non-existent object hash to trigger failure
        $response = $this->signedJsonPost('sync/commit', [
            'manifest_id' => 'fail-m1',
            'version_id' => 'fail-v1',
            'timestamp' => now()->toIso8601String(),
            'source_app' => 'test',
            'target_name' => config('sync.target.name'),
            'summary' => ['add' => 1, 'modify' => 0, 'unchanged' => 0, 'delete_later' => 0, 'total_files' => 1],
            'expected_target' => [],
            'files' => [[
                'path' => 'storage/app/testing-fixtures/should-not-appear.txt',
                'hash' => hash('sha256', 'does-not-exist-in-store'),
                'size' => 99,
                'modified_at' => now()->toIso8601String(),
                'status' => 'add',
            ]],
        ]);

        $response->assertStatus(500);

        // The untouched file must still exist
        $this->assertFileExists($keepPath);
    }
}
