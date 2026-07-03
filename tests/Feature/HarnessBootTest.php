<?php

namespace DeployCar\LaravelSyncManager\Tests\Feature;

use DeployCar\LaravelSyncManager\Tests\Concerns\SignsSyncRequests;
use DeployCar\LaravelSyncManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HarnessBootTest extends TestCase
{
    use RefreshDatabase;
    use SignsSyncRequests;

    public function test_migrations_create_the_sync_tables(): void
    {
        foreach ([
            'sync_versions',
            'sync_files',
            'sync_logs',
            'sync_operations',
            'sync_targets',
            'sync_settings',
            'sync_file_objects',
            'sync_manifests',
            'sync_target_states',
        ] as $table) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasTable($table),
                "Expected table [{$table}] to exist after migrations.",
            );
        }
    }

    public function test_receiver_rejects_request_without_token(): void
    {
        $this->postJson('/sync/commit', [])->assertUnauthorized();
    }

    public function test_receiver_checkobjects_accepts_a_signed_request(): void
    {
        $response = $this->signedJsonPost('sync/objects/check', [
            'hashes' => [hash('sha256', 'anything')],
        ]);

        $response->assertOk()->assertJson(['status' => 'success']);
    }
}
