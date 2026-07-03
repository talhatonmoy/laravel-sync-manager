<?php

namespace DeployCar\LaravelSyncManager\Tests\Feature;

use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Tests\Concerns\SignsSyncRequests;
use DeployCar\LaravelSyncManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReceiverSecurityTest extends TestCase
{
    use RefreshDatabase;
    use SignsSyncRequests;

    private function commitPayload(array $overrides = []): array
    {
        return array_merge([
            'manifest_id' => 'm-x',
            'version_id' => 'v-x',
            'timestamp' => now()->toIso8601String(),
            'source_app' => 'test',
            'target_name' => config('sync.target.name'),
            'parent_manifest_id' => null,
            'summary' => ['add' => 1, 'modify' => 0, 'unchanged' => 0, 'delete_later' => 0, 'total_files' => 1],
            'expected_target' => [],
            'files' => [],
        ], $overrides);
    }

    // ---------- C3: receiver rejects dangerous files ----------

    public function test_commit_rejects_env_file(): void
    {
        $blob = 'APP_KEY=base64:abc';
        $hash = hash('sha256', $blob);
        app(ObjectStoreInterface::class)->storeContents($blob, $hash);

        $response = $this->signedJsonPost('sync/commit', $this->commitPayload([
            'files' => [[
                'path' => '.env',
                'hash' => $hash,
                'size' => strlen($blob),
                'modified_at' => now()->toIso8601String(),
                'status' => 'add',
            ]],
        ]));

        $response->assertStatus(500);
        $this->assertDatabaseHas('sync_versions', [
            'version_id' => 'v-x', 'status' => 'failed',
        ]);
    }

    public function test_commit_rejects_sqlite_file(): void
    {
        $blob = 'SQLite content';
        $hash = hash('sha256', $blob);
        app(ObjectStoreInterface::class)->storeContents($blob, $hash);

        $response = $this->signedJsonPost('sync/commit', $this->commitPayload([
            'files' => [[
                'path' => 'storage/database.sqlite',
                'hash' => $hash,
                'size' => strlen($blob),
                'modified_at' => now()->toIso8601String(),
                'status' => 'add',
            ]],
        ]));

        $response->assertStatus(500);
        $this->assertDatabaseHas('sync_versions', [
            'version_id' => 'v-x', 'status' => 'failed',
        ]);
    }

    public function test_commit_allows_safe_file(): void
    {
        $blob = '<?php class SafeClass {}';
        $hash = hash('sha256', $blob);
        app(ObjectStoreInterface::class)->storeContents($blob, $hash);

        $response = $this->signedJsonPost('sync/commit', $this->commitPayload([
            'version_id' => 'v-safe',
            'files' => [[
                'path' => 'app/SafeClass.php',
                'hash' => $hash,
                'size' => strlen($blob),
                'modified_at' => now()->toIso8601String(),
                'status' => 'add',
            ]],
        ]));

        $response->assertOk()->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('sync_versions', [
            'version_id' => 'v-safe', 'status' => 'success',
        ]);
    }

    public function test_commit_respects_subtree_allow_list(): void
    {
        config()->set('sync.security.writable_subtrees', ['resources/']);

        $blob = 'view content';
        $hash = hash('sha256', $blob);
        app(ObjectStoreInterface::class)->storeContents($blob, $hash);

        $response = $this->signedJsonPost('sync/commit', $this->commitPayload([
            'files' => [[
                'path' => 'vendor/unsafe/composer-installed.php',
                'hash' => $hash,
                'size' => strlen($blob),
                'modified_at' => now()->toIso8601String(),
                'status' => 'add',
            ]],
        ]));

        $response->assertStatus(500);
        $this->assertDatabaseHas('sync_versions', [
            'version_id' => 'v-x', 'status' => 'failed',
        ]);
    }

    // ---------- C1/C2: default-key guard ----------

    public function test_token_middleware_rejects_change_me_key(): void
    {
        // Override to non-local env so the middleware's default-key guard fires.
        $this->app->detectEnvironment(fn () => 'production');

        config()->set('sync.receiver.api_key', 'change-me');

        $response = $this->withHeader('X-Sync-Key', 'change-me')
            ->postJson('/sync/commit', []);

        $response->assertStatus(403);
        $response->assertSee('default API key');
    }

    // ---------- M3: upload size limit ----------

    public function test_upload_rejects_too_large_object(): void
    {
        config()->set('sync.receiver.api_key', 'test-secret-key');

        $body = 'small-content-that-claims-to-be-huge';
        $hash = hash('sha256', $body);

        $response = $this->call('POST', '/sync/objects/'.$hash, [], [], [], [
            'HTTP_X_SYNC_KEY' => $this->syncKey(),
            'HTTP_CONTENT_LENGTH' => '52428801', // 50 MB + 1
            'CONTENT_TYPE' => 'application/octet-stream',
        ], $body);

        $response->assertStatus(413);
        $response->assertSee('exceeds the maximum');
    }
}
