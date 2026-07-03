<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\RequiresStrictProductionConfirmation;
use DeployCar\LaravelSyncManager\Contracts\LocalBackupServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use DeployCar\LaravelSyncManager\Jobs\ExecuteSyncOperationJob;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class RestoreLocalCommand extends Command
{
    use RequiresStrictProductionConfirmation;

    protected $signature = 'sync:restore-local {versionId?} {--queued} {--force}';

    protected $description = 'Restore local files from the backup created by a production pull.';

    public function handle(LocalBackupServiceInterface $localBackupService, OperationTrackerInterface $tracker): int
    {
        if (! $this->confirmStrictly()) {
            return self::FAILURE;
        }

        if ($this->option('queued')) {
            $operation = $tracker->start([
                'type' => 'restore-local',
                'message' => 'Queued local restore.',
            ]);
            ExecuteSyncOperationJob::dispatchConfigured($operation->operation_id, 'restore-local', [
                'version_id' => $this->argument('versionId'),
            ]);
            $this->info("Local restore queued. Operation ID: {$operation->operation_id}");

            return self::SUCCESS;
        }

        $reporter = new ConsoleProgressReporter($this, 'Restore local');
        $response = $localBackupService->restoreForVersion($this->argument('versionId'), $reporter->callback());
        $reporter->finish();
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
