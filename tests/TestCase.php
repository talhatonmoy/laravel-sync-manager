<?php

namespace DeployCar\LaravelSyncManager\Tests;

use DeployCar\LaravelSyncManager\SyncManagerServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $storageRoot;

    protected string $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();

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

    protected function getPackageProviders($app): array
    {
        return [
            SyncManagerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $base = sys_get_temp_dir().'/sync-manager-tests/'.uniqid('run_', true);
        $this->storageRoot = $base.'/storage';
        $this->sourceRoot = $base.'/source';

        $app['config']->set('sync.storage_root', $this->storageRoot);
        $app['config']->set('sync.source_path', $this->sourceRoot);

        // Enable receiver routes + seed a valid target so all tests work.
        $app['config']->set('sync.receiver.enabled', true);
        $app['config']->set('sync.receiver.api_key', 'test-secret-key');
        $app['config']->set('sync.target.name', 'test-target');
        $app['config']->set('sync.target.url', 'https://test-target.test');
        $app['config']->set('sync.target.api_key', 'test-secret-key');
    }
}
