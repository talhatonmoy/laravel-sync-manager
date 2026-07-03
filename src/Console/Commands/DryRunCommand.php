<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Jobs\ExecuteSyncOperationJob;
use DeployCar\LaravelSyncManager\Services\SyncSender;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class DryRunCommand extends Command
{
    protected $signature = 'sync:dry-run {target?} {--queued}';

    protected $description = 'Preview file changes before syncing.';

    public function handle(SyncSender $sender, OperationTrackerInterface $tracker): int
    {
        try {
            if ($this->option('queued')) {
                $operation = $tracker->start([
                    'type' => 'dry-run',
                    'strategy' => 'local-first',
                    'target_name' => $this->argument('target'),
                    'message' => 'Queued dry run.',
                ]);
                ExecuteSyncOperationJob::dispatchConfigured($operation->operation_id, 'dry-run', [
                    'target' => $this->argument('target'),
                ]);
                $this->info("Dry run queued. Operation ID: {$operation->operation_id}");

                return self::SUCCESS;
            }

            $reporter = new ConsoleProgressReporter($this, 'Dry run');
            $response = $sender->dryRun($this->argument('target'), $reporter->callback());
            $reporter->finish();
            $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (\DeployCar\LaravelSyncManager\Exceptions\SecurityViolationException $e) {
            $this->error('SECURITY RISK DETECTED!');
            $this->line("<fg=red>{$e->getMessage()}</>");

            return self::FAILURE;
        }
    }
}
