<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\RequiresStrictProductionConfirmation;
use DeployCar\LaravelSyncManager\Contracts\ProductionPullServiceInterface;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class PullCommand extends Command
{
    use RequiresStrictProductionConfirmation;

    protected $signature = 'sync:pull {target?} {--force}';

    protected $description = 'Pull production files to local after creating a local backup.';

    public function handle(ProductionPullServiceInterface $pullService): int
    {
        if (! $this->confirmStrictly()) {
            return self::FAILURE;
        }

        $reporter = new ConsoleProgressReporter($this, 'Production pull');
        $response = $pullService->pull($this->argument('target'), $reporter->callback());
        $reporter->finish();
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
