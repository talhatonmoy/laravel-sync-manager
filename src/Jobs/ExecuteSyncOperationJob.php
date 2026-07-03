<?php

namespace DeployCar\LaravelSyncManager\Jobs;

use DeployCar\LaravelSyncManager\Contracts\LocalBackupServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Contracts\SyncCoordinatorInterface;
use DeployCar\LaravelSyncManager\Services\SyncSender;
use DeployCar\LaravelSyncManager\Services\RollbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteSyncOperationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $operationId,
        public string $type,
        public array $payload = []
    ) {
        $this->onConnection(config('sync.queue.connection'));
        $this->onQueue(config('sync.queue.queue'));
    }

    public static function dispatchConfigured(string $operationId, string $type, array $payload = []): void
    {
        $job = new self($operationId, $type, $payload);

        if (config('sync.queue.enabled', false)) {
            dispatch($job);

            return;
        }

        dispatch_sync($job);
    }

    public function handle(
        OperationTrackerInterface $tracker,
        SyncCoordinatorInterface $coordinator,
        SyncSender $sender,
        RollbackService $rollbackService,
        LocalBackupServiceInterface $localBackupService
    ): void {
        $progress = fn (int $percent, string $stage, string $message, array $context = []) => $tracker->progress(
            $this->operationId,
            $percent,
            $stage,
            $message,
            $context
        );

        try {
            $result = match ($this->type) {
                'preview' => $coordinator->preview(
                    $this->payload['strategy'] ?? 'preview',
                    $this->payload['target'] ?? null,
                    $progress
                ),
                'apply-production-first' => $coordinator->applyProductionFirst(
                    $this->payload['target'] ?? null,
                    $progress,
                    $this->operationId
                ),
                'apply-local-first' => $coordinator->applyLocalFirst(
                    $this->payload['target'] ?? null,
                    $progress,
                    $this->operationId
                ),
                'dry-run' => $sender->dryRun(
                    $this->payload['target'] ?? null,
                    $progress
                ),
                'rollback' => $rollbackService->rollbackTo(
                    $this->payload['version_id'] ?? null,
                    $progress,
                    $this->operationId
                ),
                'undo' => $rollbackService->undoLastSync(
                    $progress,
                    $this->operationId
                ),
                'restore-local' => $localBackupService->restoreForVersion(
                    $this->payload['version_id'] ?? null,
                    $progress
                ),
                default => throw new \RuntimeException("Unsupported operation type [{$this->type}]."),
            };

            $tracker->complete($this->operationId, $result);
        } catch (\Throwable $exception) {
            $tracker->fail($this->operationId, $exception->getMessage(), [
                'result_payload' => [
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                ],
            ]);

            throw $exception;
        }
    }
}
