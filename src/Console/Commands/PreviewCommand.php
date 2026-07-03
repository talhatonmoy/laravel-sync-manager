<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Contracts\SyncCoordinatorInterface;
use DeployCar\LaravelSyncManager\Jobs\ExecuteSyncOperationJob;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class PreviewCommand extends Command
{
    protected $signature = 'sync:preview {target?} {--strategy=preview} {--queued}';

    protected $description = 'Preview sync changes using preview, production-first, or local-first strategy.';

    public function handle(SyncCoordinatorInterface $coordinator, OperationTrackerInterface $tracker): int
    {
        try {
            if ($this->option('queued')) {
                $operation = $tracker->start([
                    'type' => 'preview',
                    'strategy' => (string) $this->option('strategy'),
                    'target_name' => $this->argument('target'),
                    'message' => 'Queued preview.',
                ]);
                ExecuteSyncOperationJob::dispatchConfigured($operation->operation_id, 'preview', [
                    'strategy' => (string) $this->option('strategy'),
                    'target' => $this->argument('target'),
                ]);
                $this->info("Preview queued. Operation ID: {$operation->operation_id}");

                return self::SUCCESS;
            }

            $reporter = new ConsoleProgressReporter($this, 'Preview');
            $response = $coordinator->preview(
                (string) $this->option('strategy'),
                $this->argument('target'),
                $reporter->callback()
            );

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
