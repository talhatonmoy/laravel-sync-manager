<?php

namespace DeployCar\LaravelSyncManager;

use DeployCar\LaravelSyncManager\Console\Commands\DryRunCommand;
use DeployCar\LaravelSyncManager\Console\Commands\HistoryCommand;
use DeployCar\LaravelSyncManager\Console\Commands\PreviewCommand;
use DeployCar\LaravelSyncManager\Console\Commands\PruneObjectsCommand;
use DeployCar\LaravelSyncManager\Console\Commands\PullCommand;
use DeployCar\LaravelSyncManager\Console\Commands\RollbackCommand;
use DeployCar\LaravelSyncManager\Console\Commands\RestoreLocalCommand;
use DeployCar\LaravelSyncManager\Console\Commands\RunCommand;
use DeployCar\LaravelSyncManager\Console\Commands\ScanCommand;
use DeployCar\LaravelSyncManager\Console\Commands\ScheduleCommand;
use DeployCar\LaravelSyncManager\Console\Commands\SendCommand;
use DeployCar\LaravelSyncManager\Console\Commands\StatusCommand;
use DeployCar\LaravelSyncManager\Contracts\ApplyServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\ChangeDetectorInterface;
use DeployCar\LaravelSyncManager\Contracts\FileScannerInterface;
use DeployCar\LaravelSyncManager\Contracts\IgnoreManagerInterface;
use DeployCar\LaravelSyncManager\Contracts\IncrementalTransportInterface;
use DeployCar\LaravelSyncManager\Contracts\LocalBackupServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\ManifestRepositoryInterface;
use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Contracts\ProductionPullServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\RollbackServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\SecurityGateInterface;
use DeployCar\LaravelSyncManager\Contracts\StateRepositoryInterface;
use DeployCar\LaravelSyncManager\Contracts\SyncCoordinatorInterface;
use DeployCar\LaravelSyncManager\Contracts\SyncSenderInterface;
use DeployCar\LaravelSyncManager\Contracts\VersionManagerInterface;
use DeployCar\LaravelSyncManager\Middleware\AuthorizeSyncManager;
use DeployCar\LaravelSyncManager\Middleware\EnsureValidSyncToken;
use DeployCar\LaravelSyncManager\Services\ApplyService;
use DeployCar\LaravelSyncManager\Services\ChangeDetector;
use DeployCar\LaravelSyncManager\Services\FileScanner;
use DeployCar\LaravelSyncManager\Services\IgnoreManager;
use DeployCar\LaravelSyncManager\Services\IncrementalTransport;
use DeployCar\LaravelSyncManager\Services\LocalBackupService;
use DeployCar\LaravelSyncManager\Services\ManifestRepository;
use DeployCar\LaravelSyncManager\Services\ObjectStore;
use DeployCar\LaravelSyncManager\Services\OperationTracker;
use DeployCar\LaravelSyncManager\Services\ProductionPullService;
use DeployCar\LaravelSyncManager\Services\ProtocolSecurity;
use DeployCar\LaravelSyncManager\Services\RollbackService;
use DeployCar\LaravelSyncManager\Services\RuntimeConfigurationLoader;
use DeployCar\LaravelSyncManager\Services\SchemaReadiness;
use DeployCar\LaravelSyncManager\Services\SecurityGate;
use DeployCar\LaravelSyncManager\Services\StateRepository;
use DeployCar\LaravelSyncManager\Services\SyncCoordinator;
use DeployCar\LaravelSyncManager\Services\TargetResolver;
use DeployCar\LaravelSyncManager\Services\SyncSender;
use DeployCar\LaravelSyncManager\Services\VersionManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SyncManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sync.php', 'sync');

        $this->app->bind(SecurityGateInterface::class, SecurityGate::class);
        $this->app->bind(IgnoreManagerInterface::class, IgnoreManager::class);
        $this->app->bind(FileScannerInterface::class, FileScanner::class);
        $this->app->bind(ChangeDetectorInterface::class, ChangeDetector::class);
        $this->app->bind(ObjectStoreInterface::class, ObjectStore::class);
        $this->app->bind(ManifestRepositoryInterface::class, ManifestRepository::class);
        $this->app->bind(StateRepositoryInterface::class, StateRepository::class);
        $this->app->bind(IncrementalTransportInterface::class, IncrementalTransport::class);
        $this->app->bind(ApplyServiceInterface::class, ApplyService::class);
        $this->app->bind(SyncSenderInterface::class, SyncSender::class);
        $this->app->bind(VersionManagerInterface::class, VersionManager::class);
        $this->app->bind(OperationTrackerInterface::class, OperationTracker::class);
        $this->app->bind(RollbackServiceInterface::class, RollbackService::class);
        $this->app->bind(LocalBackupServiceInterface::class, LocalBackupService::class);
        $this->app->bind(ProductionPullServiceInterface::class, ProductionPullService::class);
        $this->app->bind(SyncCoordinatorInterface::class, SyncCoordinator::class);
        $this->app->singleton(TargetResolver::class);
        $this->app->singleton(RuntimeConfigurationLoader::class);
        $this->app->singleton(SchemaReadiness::class);
        $this->app->singleton(ProtocolSecurity::class);
    }

    public function boot(Router $router): void
    {
        $this->app->make(RuntimeConfigurationLoader::class)->load();

        $router->aliasMiddleware('sync-manager.token', EnsureValidSyncToken::class);
        $router->aliasMiddleware('sync-manager.authorize', AuthorizeSyncManager::class);

        Gate::define('viewSyncManager', function ($user = null) {
            return false;
        });

        $this->publishes([
            __DIR__.'/../config/sync.php' => config_path('sync.php'),
        ], 'sync-manager-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sync-manager');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScanCommand::class,
                SendCommand::class,
                RunCommand::class,
                DryRunCommand::class,
                HistoryCommand::class,
                PreviewCommand::class,
                PruneObjectsCommand::class,
                PullCommand::class,
                RollbackCommand::class,
                RestoreLocalCommand::class,
                ScheduleCommand::class,
                StatusCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (! config('sync.schedule.enabled', false)) {
                return;
            }

            $event = $schedule->command('sync:scheduled-run');
            $frequency = (string) config('sync.schedule.frequency', 'hourly');

            if (method_exists($event, $frequency)) {
                $event->{$frequency}();
            } else {
                $event->hourly();
            }
        });
    }
}
