<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\CommandUX;
use DeployCar\LaravelSyncManager\Console\Concerns\RequiresStrictProductionConfirmation;
use DeployCar\LaravelSyncManager\Services\RollbackService;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class RollbackCommand extends Command
{
    use RequiresStrictProductionConfirmation;
    use CommandUX;

    protected $signature = 'sync:rollback {versionId?} {--undo} {--force}';

    protected $description = 'Rollback to a prior tracked state or undo the latest sync.';

    public function handle(RollbackService $rollbackService): int
    {
        $startTime = microtime(true);

        try {
            if (! $this->confirmStrictly()) {
                return self::FAILURE;
            }

            $reporter = new ConsoleProgressReporter($this, 'Rollback');
            $response = $this->option('undo')
                ? $rollbackService->undoLastSync($reporter->callback())
                : $rollbackService->rollbackTo($this->argument('versionId'), $reporter->callback());

            $reporter->finish();

            $this->guardTimeout($startTime, (int) env('SYNC_MANAGER_CLI_TIMEOUT_SECONDS', 600));

            return $this->renderSuccess($response);
        } catch (\Throwable $e) {
            return $this->renderError($e);
        }
    }
}
