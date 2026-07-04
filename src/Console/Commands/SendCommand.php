<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\RequiresStrictProductionConfirmation;
use DeployCar\LaravelSyncManager\Contracts\SyncCoordinatorInterface;
use DeployCar\LaravelSyncManager\Services\SyncSender;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class SendCommand extends Command
{
    use RequiresStrictProductionConfirmation;

    protected $signature = 'sync:send {target?} {--all} {--no-delete} {--force}';

    protected $description = 'Detect changed files and push only the required objects to the configured target.';

    public function handle(SyncSender $sender): int
    {
        try {
            if (! $this->confirmStrictly()) {
                return self::FAILURE;
            }

            config(['sync.no_delete' => $this->option('no-delete')]);
            if ($this->option('all')) {
                $response = $sender->sendAll();
                $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            config(['sync.no_delete' => $this->option('no-delete')]);
            $reporter = new ConsoleProgressReporter($this, 'Sync send');
            $response = app(SyncCoordinatorInterface::class)->applyLocalFirst(
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
