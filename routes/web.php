<?php

use DeployCar\LaravelSyncManager\Controllers\ActionController;
use DeployCar\LaravelSyncManager\Controllers\ConfigurationController;
use DeployCar\LaravelSyncManager\Controllers\DashboardController;
use DeployCar\LaravelSyncManager\Controllers\OperationController;
use Illuminate\Support\Facades\Route;

if (config('sync.ui.enabled', true)) {
    Route::middleware(['web', 'sync-manager.authorize'])
        ->prefix(trim((string) config('sync.ui.route_prefix', 'sync'), '/'))
        ->group(function (): void {
            Route::get('/local', [DashboardController::class, 'local'])->name('sync-manager.local');
            Route::get('/production', [DashboardController::class, 'production'])->name('sync-manager.production');
            Route::post('/preview', [ActionController::class, 'preview'])->name('sync-manager.preview');
            Route::post('/apply', [ActionController::class, 'apply'])->name('sync-manager.apply');
            Route::post('/run', [ActionController::class, 'sync'])->name('sync-manager.run');
            Route::post('/dry-run', [ActionController::class, 'dryRun'])->name('sync-manager.dry-run');
            Route::post('/rollback', [ActionController::class, 'rollback'])->name('sync-manager.rollback');
            Route::post('/restore-local', [ActionController::class, 'restoreLocal'])->name('sync-manager.restore-local');
            Route::post('/undo', [ActionController::class, 'undo'])->name('sync-manager.undo');
            Route::post('/operations', [OperationController::class, 'start'])->name('sync-manager.operations.start');
            Route::get('/operations/{operationId}', [OperationController::class, 'show'])->name('sync-manager.operations.show');
            Route::post('/configuration/targets', [ConfigurationController::class, 'saveTarget'])->name('sync-manager.configuration.targets.save');
            Route::delete('/configuration/targets/{targetId}', [ConfigurationController::class, 'deleteTarget'])->name('sync-manager.configuration.targets.delete');
            Route::post('/configuration/settings', [ConfigurationController::class, 'saveSettings'])->name('sync-manager.configuration.settings.save');
            Route::get('/configuration/ignore', [ConfigurationController::class, 'getIgnorePatterns'])->name('sync-manager.configuration.ignore.index');
            Route::post('/configuration/ignore', [ConfigurationController::class, 'addIgnorePattern'])->name('sync-manager.configuration.ignore.store');
            Route::delete('/configuration/ignore', [ConfigurationController::class, 'removeIgnorePattern'])->name('sync-manager.configuration.ignore.delete');
        });
}
