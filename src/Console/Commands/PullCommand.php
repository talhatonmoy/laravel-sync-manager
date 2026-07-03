<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\RequiresStrictProductionConfirmation;
use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Contracts\ProductionPullServiceInterface;
use DeployCar\LaravelSyncManager\Jobs\ExecuteSyncOperationJob;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class PullCommand extends Command
{
    use RequiresStrictProductionConfirmation;

    protected $signature = 'sync:pull {target?} {--queued} {--force}';

    protected $description = 'Pull production files to local after creating a local backup.';

    public function handle(ProductionPullServiceInterface $pullService, OperationTrackerInterface $tracker): int
    {
        if (! $this->confirmStrictly()) {
            return self::FAILURE;
        }

        if ($this->option('queued')) {
            $operation = $tracker->start([
                'type' => 'apply-production-first',
                'strategy' => 'production-first',
                'target_name' => $this->argument('target'),
                'message' => 'Queued production pull.',
            ]);
            ExecuteSyncOperationJob::dispatchConfigured($operation->operation_id, 'apply-production-first', [
                'target' => $this->argument('target'),
            ]);
            $this->info("Production pull queued. Operation ID: {$operation->operation_id}");

            return self::SUCCESS;
        }

        $reporter = new ConsoleProgressReporter($this, 'Production pull');
        $response = $pullService->pull($this->argument('target'), $reporter->callback());
        $reporter->finish();
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
