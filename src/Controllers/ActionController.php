<?php

namespace DeployCar\LaravelSyncManager\Controllers;

use DeployCar\LaravelSyncManager\Contracts\LocalBackupServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\SyncCoordinatorInterface;
use DeployCar\LaravelSyncManager\Services\RollbackService;
use DeployCar\LaravelSyncManager\Services\SyncSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ActionController extends Controller
{
    public function preview(Request $request, SyncCoordinatorInterface $coordinator): RedirectResponse
    {
        $response = $coordinator->preview(
            $request->string('strategy')->toString() ?: 'preview',
            $request->string('target')->toString() ?: null
        );

        return back()->with('sync_manager_status', $response);
    }

    public function apply(Request $request, SyncCoordinatorInterface $coordinator): RedirectResponse
    {
        $strategy = $request->string('strategy')->toString() ?: 'preview';

        $response = match ($strategy) {
            'production-first' => $coordinator->applyProductionFirst($request->string('target')->toString() ?: null),
            'local-first' => $coordinator->applyLocalFirst($request->string('target')->toString() ?: null),
            default => $coordinator->preview('preview', $request->string('target')->toString() ?: null),
        };

        return back()->with('sync_manager_status', $response);
    }

    public function sync(Request $request, SyncSender $sender): RedirectResponse
    {
        $response = $request->boolean('all_targets')
            ? $sender->sendAll()
            : $sender->send($request->string('target')->toString() ?: null);

        return back()->with('sync_manager_status', $response);
    }

    public function dryRun(Request $request, SyncSender $sender): RedirectResponse
    {
        return back()->with('sync_manager_status', $request->boolean('all_targets')
            ? $sender->dryRunAll()
            : $sender->dryRun($request->string('target')->toString() ?: null));
    }

    public function rollback(Request $request, RollbackService $rollbackService): RedirectResponse
    {
        $response = $rollbackService->rollbackTo($request->string('version_id')->toString() ?: null);

        return back()->with('sync_manager_status', $response);
    }

    public function undo(RollbackService $rollbackService): RedirectResponse
    {
        return back()->with('sync_manager_status', $rollbackService->undoLastSync());
    }

    public function restoreLocal(Request $request, LocalBackupServiceInterface $localBackupService): RedirectResponse
    {
        return back()->with('sync_manager_status', $localBackupService->restoreForVersion(
            $request->string('version_id')->toString() ?: null
        ));
    }
}
