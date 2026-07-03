<?php

use DeployCar\LaravelSyncManager\Controllers\ReceiverController;
use Illuminate\Support\Facades\Route;

if (config('sync.receiver.enabled', true)) {
    Route::middleware(['api', 'throttle:60,1', 'sync-manager.token'])
        ->prefix(trim((string) config('sync.receiver.route_prefix', 'sync'), '/'))
        ->group(function (): void {
            Route::get('/state', [ReceiverController::class, 'state'])->name('sync-manager.state');
            Route::post('/objects/check', [ReceiverController::class, 'checkObjects'])->name('sync-manager.objects.check');
            Route::post('/objects/{hash}', [ReceiverController::class, 'uploadObject'])->name('sync-manager.objects.upload')
                ->where('hash', '[a-f0-9]{64}');
            Route::get('/objects/{hash}', [ReceiverController::class, 'downloadObject'])->name('sync-manager.objects.download')
                ->where('hash', '[a-f0-9]{64}');
            Route::post('/commit', [ReceiverController::class, 'commit'])->name('sync-manager.commit');
        });
}
