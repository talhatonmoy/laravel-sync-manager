<?php

namespace DeployCar\LaravelSyncManager\Tests\Feature;

use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Tests\Concerns\SignsSyncRequests;
use DeployCar\LaravelSyncManager\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Phase3FeatureTest extends TestCase
{
    use RefreshDatabase;
    use SignsSyncRequests;

    // ---------- L2: delete_later physically unlinks file ----------

    public function test_delete_later_removes_file_from_disk(): void
    {
        $blob = 'file-to-delete';
        $hash = hash('sha256', $blob);
        app(ObjectStoreInterface::class)->storeContents($blob, $hash);

        $relativePath = 'storage/app/testing-fixtures/to-be-deleted.txt';
        $deletePath = base_path($relativePath);
        (new Filesystem())->ensureDirectoryExists(dirname($deletePath));
        file_put_contents($deletePath, $blob);

        $this->assertFileExists($deletePath);

        $response = $this->signedJsonPost('sync/commit', [
            'manifest_id' => 'del-m1',
            'version_id' => 'del-v1',
            'timestamp' => now()->toIso8601String(),
            'source_app' => 'test',
            'target_name' => config('sync.target.name'),
            'summary' => ['add' => 0, 'modify' => 0, 'unchanged' => 0, 'delete_later' => 1, 'total_files' => 1],
            'expected_target' => [],
            'files' => [[
                'path' => $relativePath,
                'hash' => $hash,
                'size' => strlen($blob),
                'modified_at' => now()->toIso8601String(),
                'status' => 'delete_later',
            ]],
        ]);

        $response->assertOk();
        $this->assertFileDoesNotExist($deletePath);
    }

    // ---------- C1: receiver explicit opt-in works ----------

    public function test_receiver_enabled_with_config_processes_requests(): void
    {
        $blob = 'opt-in-test';
        $hash = hash('sha256', $blob);
        app(ObjectStoreInterface::class)->storeContents($blob, $hash);

        $response = $this->signedJsonPost('sync/commit', [
            'manifest_id' => 'optin-m1',
            'version_id' => 'optin-v1',
            'timestamp' => now()->toIso8601String(),
            'source_app' => 'test',
            'target_name' => config('sync.target.name'),
            'summary' => ['add' => 1, 'modify' => 0, 'unchanged' => 0, 'delete_later' => 0, 'total_files' => 1],
            'expected_target' => [],
            'files' => [[
                'path' => 'app/OptInTest.php',
                'hash' => $hash,
                'size' => strlen($blob),
                'modified_at' => now()->toIso8601String(),
                'status' => 'add',
            ]],
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);
    }
}
