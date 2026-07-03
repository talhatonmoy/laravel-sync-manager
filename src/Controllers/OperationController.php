<?php

namespace DeployCar\LaravelSyncManager\Controllers;

use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Jobs\ExecuteSyncOperationJob;
use DeployCar\LaravelSyncManager\Services\SchemaReadiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OperationController extends Controller
{
    public function start(Request $request, OperationTrackerInterface $tracker, SchemaReadiness $schemaReadiness): JsonResponse
    {
        if (! $schemaReadiness->hasOperations()) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => $schemaReadiness->migrationMessage(),
            ], 409);
        }

        $data = $request->validate([
            'type' => ['required', 'string'],
            'strategy' => ['nullable', 'string'],
            'target' => ['nullable', 'string'],
            'version_id' => ['nullable', 'string'],
        ]);

        $operation = $tracker->start([
            'type' => $data['type'],
            'strategy' => $data['strategy'] ?? null,
            'target_name' => $data['target'] ?? null,
            'message' => 'Operation queued.',
            'metadata' => [
                'payload' => $data,
                'queued_via' => 'dashboard',
            ],
        ]);

        ExecuteSyncOperationJob::dispatchConfigured($operation->operation_id, $data['type'], $data);

        return new JsonResponse([
            'status' => 'queued',
            'operation_id' => $operation->operation_id,
        ]);
    }

    public function show(string $operationId, OperationTrackerInterface $tracker, SchemaReadiness $schemaReadiness): JsonResponse
    {
        if (! $schemaReadiness->hasOperations()) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => $schemaReadiness->migrationMessage(),
            ], 409);
        }

        $operation = $tracker->find($operationId);

        abort_if(! $operation, 404);

        return new JsonResponse([
            'operation_id' => $operation->operation_id,
            'type' => $operation->type,
            'strategy' => $operation->strategy,
            'target_name' => $operation->target_name,
            'status' => $operation->status,
            'stage' => $operation->stage,
            'progress' => $operation->progress,
            'message' => $operation->message,
            'result' => $operation->result_payload,
            'sync_version_id' => $operation->sync_version_id,
            'started_at' => optional($operation->started_at)->toIso8601String(),
            'completed_at' => optional($operation->completed_at)->toIso8601String(),
        ]);
    }
}
