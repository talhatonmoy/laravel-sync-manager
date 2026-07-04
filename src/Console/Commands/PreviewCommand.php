<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\CommandUX;
use DeployCar\LaravelSyncManager\Contracts\SyncCoordinatorInterface;
use DeployCar\LaravelSyncManager\Support\ConsoleProgressReporter;
use Illuminate\Console\Command;

class PreviewCommand extends Command
{
    use CommandUX;

    protected $signature = 'sync:preview {target?} {--strategy=preview}';

    protected $description = 'Preview sync changes using preview, production-first, or local-first strategy.';

    public function handle(SyncCoordinatorInterface $coordinator): int
    {
        $startTime = microtime(true);

        try {
            $reporter = new ConsoleProgressReporter($this, 'Preview');
            $response = $coordinator->preview(
                (string) $this->option('strategy'),
                $this->argument('target'),
                $reporter->callback()
            );

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
