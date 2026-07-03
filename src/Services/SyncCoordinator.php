<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\ProductionPullServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\SyncCoordinatorInterface;
use RuntimeException;

class SyncCoordinator implements SyncCoordinatorInterface
{
    public function __construct(
        protected SyncSender $sender,
        protected ProductionPullServiceInterface $productionPullService
    ) {
    }

    public function preview(string $strategy = 'preview', ?string $targetName = null, ?callable $progress = null): array
    {
        return match ($strategy) {
            'production-first' => $this->productionPullService->preview($targetName, 'production-first', $progress),
            'local-first' => array_merge($this->sender->dryRun($targetName, $progress), [
                'strategy' => 'local-first',
            ]),
            'preview' => array_merge($this->sender->dryRun($targetName, $progress), [
                'strategy' => 'preview',
            ]),
            default => throw new RuntimeException("Unsupported sync strategy [{$strategy}]."),
        };
    }

    public function applyProductionFirst(?string $targetName = null, ?callable $progress = null, ?string $operationId = null): array
    {
        return $this->productionPullService->pull($targetName, $progress, $operationId);
    }

    public function applyLocalFirst(?string $targetName = null, ?callable $progress = null, ?string $operationId = null): array
    {
        return $this->sender->send($targetName, $progress, $operationId);
    }
}
