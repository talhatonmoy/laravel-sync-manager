<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\RequiresStrictProductionConfirmation;
use DeployCar\LaravelSyncManager\Contracts\SyncCoordinatorInterface;
use DeployCar\LaravelSyncManager\Services\SyncSender;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class RunCommand extends Command
{
    use RequiresStrictProductionConfirmation;

    protected $signature = 'sync:run {target?} {--all} {--no-delete} {--force}';

    protected $description = 'Alias for sync:send.';

    public function handle(SyncSender $sender): int
    {
        if (! $this->confirmStrictly()) {
            return self::FAILURE;
        }

        config(['sync.no_delete' => $this->option('no-delete')]);
        if ($this->option('all')) {
            $response = $sender->sendAll();
            $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $reporter = new ConsoleProgressReporter($this, 'Sync run');
        $response = app(SyncCoordinatorInterface::class)->applyLocalFirst(
            $this->argument('target'),
            $reporter->callback()
        );
        $reporter->finish();
        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
