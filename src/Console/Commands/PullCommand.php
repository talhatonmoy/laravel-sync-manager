<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\CommandUX;
use DeployCar\LaravelSyncManager\Console\Concerns\RequiresStrictProductionConfirmation;
use DeployCar\LaravelSyncManager\Contracts\ProductionPullServiceInterface;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class PullCommand extends Command
{
    use RequiresStrictProductionConfirmation;
    use CommandUX;

    protected $signature = 'sync:pull {target?} {--force}';

    protected $description = 'Pull production files to local after creating a local backup.';

    public function handle(ProductionPullServiceInterface $pullService): int
    {
        $startTime = microtime(true);

        try {
            if (! $this->confirmStrictly()) {
                return self::FAILURE;
            }

            $reporter = new ConsoleProgressReporter($this, 'Production pull');
            $response = $pullService->pull($this->argument('target'), $reporter->callback());
            $reporter->finish();

            $this->guardTimeout($startTime, (int) env('SYNC_MANAGER_CLI_TIMEOUT_SECONDS', 600));

            return $this->renderSuccess($response);
        } catch (\Throwable $e) {
            return $this->renderError($e);
        }
    }
}
