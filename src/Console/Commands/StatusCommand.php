<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Contracts\OperationTrackerInterface;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'sync:status {operationId}';

    protected $description = 'Show the current status of a queued sync operation.';

    public function handle(OperationTrackerInterface $tracker): int
    {
        $operation = $tracker->find((string) $this->argument('operationId'));

        if (! $operation) {
            $this->error('Operation not found.');

            return self::FAILURE;
        }

        $this->table(
            ['Operation', 'Type', 'Status', 'Stage', 'Progress', 'Message', 'Started', 'Completed'],
            [[
                $operation->operation_id,
                $operation->type,
                $operation->status,
                $operation->stage,
                $operation->progress.'%',
                $operation->message,
                optional($operation->started_at)->toDateTimeString(),
                optional($operation->completed_at)->toDateTimeString(),
            ]]
        );

        if ($operation->result_payload) {
            $this->line(json_encode($operation->result_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
