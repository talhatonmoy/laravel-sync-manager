<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\RequiresStrictProductionConfirmation;
use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Jobs\ExecuteSyncOperationJob;
use DeployCar\LaravelSyncManager\Services\RollbackService;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class RollbackCommand extends Command
{
    use RequiresStrictProductionConfirmation;

    protected $signature = 'sync:rollback {versionId?} {--undo} {--queued} {--force}';

    protected $description = 'Rollback to a prior tracked state or undo the latest sync.';

    public function handle(RollbackService $rollbackService, OperationTrackerInterface $tracker): int
    {
        if (! $this->confirmStrictly()) {
            return self::FAILURE;
        }

        if ($this->option('queued')) {
            $type = $this->option('undo') ? 'undo' : 'rollback';
            $operation = $tracker->start([
                'type' => $type,
                'message' => $this->option('undo') ? 'Queued undo operation.' : 'Queued rollback operation.',
            ]);
            ExecuteSyncOperationJob::dispatchConfigured($operation->operation_id, $type, [
                'version_id' => $this->argument('versionId'),
            ]);
            $this->info("Rollback queued. Operation ID: {$operation->operation_id}");

            return self::SUCCESS;
        }

        $reporter = new ConsoleProgressReporter($this, 'Rollback');
        $response = $this->option('undo')
            ? $rollbackService->undoLastSync($reporter->callback())
            : $rollbackService->rollbackTo($this->argument('versionId'), $reporter->callback());

        $reporter->finish();
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
