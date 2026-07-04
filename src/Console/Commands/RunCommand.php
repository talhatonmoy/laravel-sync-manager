<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\CommandUX;
use DeployCar\LaravelSyncManager\Console\Concerns\RequiresStrictProductionConfirmation;
use DeployCar\LaravelSyncManager\Contracts\SyncCoordinatorInterface;
use DeployCar\LaravelSyncManager\Services\SyncSender;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class RunCommand extends Command
{
    use RequiresStrictProductionConfirmation;
    use CommandUX;

    protected $signature = 'sync:run {target?} {--all} {--no-delete} {--force}';

    protected $description = 'Alias for sync:send.';

    public function handle(SyncSender $sender): int
    {
        $startTime = microtime(true);

        try {
            if (! $this->confirmStrictly()) {
                return self::FAILURE;
            }

            config(['sync.no_delete' => $this->option('no-delete')]);

            if ($this->option('all')) {
                $response = $sender->sendAll();
                return $this->renderSuccess($response);
            }

            $reporter = new ConsoleProgressReporter($this, 'Sync run');
            $response = app(SyncCoordinatorInterface::class)->applyLocalFirst(
                $this->argument('target'),
                $reporter->callback()
            );
            $reporter->finish();

            $this->guardTimeout($startTime, (int) env('SYNC_MANAGER_CLI_TIMEOUT_SECONDS', 600));

            return $this->renderSuccess($response);
        } catch (\Throwable $e) {
            return $this->renderError($e);
        }
    }
}
