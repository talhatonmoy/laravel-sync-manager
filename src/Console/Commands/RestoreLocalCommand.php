<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\CommandUX;
use DeployCar\LaravelSyncManager\Console\Concerns\RequiresStrictProductionConfirmation;
use DeployCar\LaravelSyncManager\Contracts\LocalBackupServiceInterface;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class RestoreLocalCommand extends Command
{
    use RequiresStrictProductionConfirmation;
    use CommandUX;

    protected $signature = 'sync:restore-local {versionId?} {--force}';

    protected $description = 'Restore local files from the backup created by a production pull.';

    public function handle(LocalBackupServiceInterface $localBackupService): int
    {
        $startTime = microtime(true);

        try {
            if (! $this->confirmStrictly()) {
                return self::FAILURE;
            }

            $reporter = new ConsoleProgressReporter($this, 'Restore local');
            $response = $localBackupService->restoreForVersion($this->argument('versionId'), $reporter->callback());
            $reporter->finish();

            $this->guardTimeout($startTime, (int) env('SYNC_MANAGER_CLI_TIMEOUT_SECONDS', 600));

            return $this->renderSuccess($response);
        } catch (\Throwable $e) {
            return $this->renderError($e);
        }
    }
}
