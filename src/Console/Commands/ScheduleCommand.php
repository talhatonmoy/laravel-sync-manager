<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Services\SyncSender;
use Illuminate\Console\Command;

class ScheduleCommand extends Command
{
    protected $signature = 'sync:scheduled-run {--all} {--queued}';

    protected $description = 'Run the configured scheduled sync operation.';

    public function handle(SyncSender $sender): int
    {
        $all = $this->option('all') || config('sync.schedule.all_targets', true);
        $queued = $this->option('queued') || config('sync.schedule.queue', true);

        $result = $queued
            ? $sender->dispatch($all)
            : ($all ? $sender->sendAll() : $sender->send());

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
