<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Contracts\SyncCoordinatorInterface;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class PreviewCommand extends Command
{
    protected $signature = 'sync:preview {target?} {--strategy=preview}';

    protected $description = 'Preview sync changes using preview, production-first, or local-first strategy.';

    public function handle(SyncCoordinatorInterface $coordinator): int
    {
        try {
            $reporter = new ConsoleProgressReporter($this, 'Preview');
            $response = $coordinator->preview(
                (string) $this->option('strategy'),
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
