<?php

namespace DeployCar\LaravelSyncManager\Controllers;

use DeployCar\LaravelSyncManager\Models\SyncOperation;
use DeployCar\LaravelSyncManager\Models\SyncVersion;
use DeployCar\LaravelSyncManager\Services\ConfigurationRepository;
use DeployCar\LaravelSyncManager\Services\SchemaReadiness;
use DeployCar\LaravelSyncManager\Services\TargetResolver;
use DeployCar\LaravelSyncManager\Services\VersionManager;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function local(
        TargetResolver $targetResolver,
        ConfigurationRepository $configurationRepository,
        SchemaReadiness $schemaReadiness
    )
    {
        $targets = $targetResolver->all();
        $defaultTarget = $targetResolver->first();
        $versions = SyncVersion::query()->latest('id')->limit(15)->get();
        $operations = $schemaReadiness->hasOperations()
            ? SyncOperation::query()->latest('id')->limit(12)->get()
            : collect();

        return view('sync-manager::local', [
            'versions' => $versions,
            'targetUrl' => $defaultTarget['url'] ?? config('sync.target.url'),
            'targets' => $targets,
            'managedTargets' => $configurationRepository->targets(),
            'settings' => $configurationRepository->dashboardSettings(),
            'defaultStrategy' => (string) config('sync.ui.default_strategy', 'preview'),
            'activeOperation' => $schemaReadiness->hasOperations()
                ? SyncOperation::query()->whereIn('status', ['queued', 'running'])->latest('id')->first()
                : null,
            'operations' => $operations,
            'migrationWarning' => $schemaReadiness->hasOperations() && $schemaReadiness->hasTargets() && $schemaReadiness->hasSettings()
                ? null
                : $schemaReadiness->migrationMessage(),
            'summary' => [
                'targets' => count($targets),
                'versions' => $versions->count(),
                'failed_versions' => SyncVersion::query()->where('status', 'failed')->count(),
                'recent_changes' => (int) collect($versions->take(5))->sum(fn ($version) => (int) data_get($version->summary, 'add', 0) + (int) data_get($version->summary, 'modify', 0)),
            ],
        ]);
    }

    public function production(SchemaReadiness $schemaReadiness)
    {
        $versions = SyncVersion::query()->latest('id')->limit(15)->get();
        $operations = $schemaReadiness->hasOperations()
            ? SyncOperation::query()->latest('id')->limit(12)->get()
            : collect();

        return view('sync-manager::production', [
            'versions' => $versions,
            'operations' => $operations,
            'settings' => app(ConfigurationRepository::class)->dashboardSettings(),
            'managedTargets' => app(ConfigurationRepository::class)->targets(),
            'currentState' => app(VersionManager::class)->currentState(),
            'activeOperation' => $schemaReadiness->hasOperations()
                ? SyncOperation::query()->whereIn('status', ['queued', 'running'])->latest('id')->first()
                : null,
            'migrationWarning' => $schemaReadiness->hasOperations() && $schemaReadiness->hasTargets() && $schemaReadiness->hasSettings()
                ? null
                : $schemaReadiness->migrationMessage(),
        ]);
    }
}
