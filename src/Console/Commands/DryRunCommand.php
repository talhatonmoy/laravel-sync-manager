<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Services\SyncSender;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class DryRunCommand extends Command
{
    protected $signature = 'sync:dry-run {target?}';

    protected $description = 'Preview file changes before syncing.';

    public function handle(SyncSender $sender): int
    {
        try {
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
