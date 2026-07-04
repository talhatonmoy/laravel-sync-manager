<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\CommandUX;
use DeployCar\LaravelSyncManager\Services\SyncSender;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class DryRunCommand extends Command
{
    use CommandUX;

    protected $signature = 'sync:dry-run {target?}';

    protected $description = 'Preview file changes before syncing.';

    public function handle(SyncSender $sender): int
    {
        $startTime = microtime(true);

        try {
            $reporter = new ConsoleProgressReporter($this, 'Dry run');
            $response = $sender->dryRun($this->argument('target'), $reporter->callback());
            $reporter->finish();

            $this->guardTimeout($startTime, (int) env('SYNC_MANAGER_CLI_TIMEOUT_SECONDS', 600));

            return $this->renderSuccess($response);
        } catch (\DeployCar\LaravelSyncManager\Exceptions\SecurityViolationException $e) {
            $this->error('SECURITY RISK DETECTED!');
            $this->line("<fg=red>{$e->getMessage()}</>");

            return self::FAILURE;
        } catch (\Throwable $e) {
            return $this->renderError($e);
        }
    }
}
