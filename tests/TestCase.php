<?php

namespace DeployCar\LaravelSyncManager\Tests;

use DeployCar\LaravelSyncManager\SyncManagerServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Isolated storage root for object store, backups, and manifests.
     */
    protected string $storageRoot;

    /**
     * Isolated source root that stands in for the synced project.
     */
    protected string $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Testbench resolves app.env as "testing" during createApplication,
        // but AuthorizeSyncManager only bypasses auth in "local" env.
        // Override it here so web-route tests can reach the controller.
        $this->app->detectEnvironment(static fn () => 'local');

        $files = new Filesystem();
        $files->ensureDirectoryExists($this->storageRoot);
        $files->ensureDirectoryExists($this->sourceRoot);
    }

    protected function tearDown(): void
    {
        $files = new Filesystem();

        foreach ([$this->storageRoot, $this->sourceRoot] as $path) {
            if (isset($path) && $files->isDirectory($path)) {
                $files->deleteDirectory($path);
            }
        }

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SyncManagerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Per-test isolated roots so file-writing services never touch the repo.
        $base = sys_get_temp_dir().'/sync-manager-tests/'.uniqid('run_', true);
        $this->storageRoot = $base.'/storage';
        $this->sourceRoot = $base.'/source';

        $app['config']->set('sync.storage_root', $this->storageRoot);
        $app['config']->set('sync.source_path', $this->sourceRoot);

        // Enable receiver routes so tests can exercise them.
        $app['config']->set('sync.receiver.enabled', true);

        // A strong, non-default key so auth/protocol tests exercise the happy path.
        $app['config']->set('sync.receiver.api_key', 'test-secret-key');
        $app['config']->set('sync.target.name', 'test-target');
    }
}
