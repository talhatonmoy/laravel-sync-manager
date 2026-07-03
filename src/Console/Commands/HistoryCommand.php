<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Models\SyncVersion;
use Illuminate\Console\Command;

class HistoryCommand extends Command
{
    protected $signature = 'sync:history {--status=}';

    protected $description = 'List recorded sync versions.';

    public function handle(): int
    {
        $versions = SyncVersion::query()
            ->when($this->option('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->limit(20)
            ->get(['version_id', 'operation', 'direction', 'status', 'target_name', 'created_at']);

        $this->table(
            ['Version', 'Operation', 'Direction', 'Status', 'Target', 'Created'],
            $versions->map(static fn ($version) => [
                $version->version_id,
                $version->operation,
                $version->direction,
                $version->status,
                $version->target_name,
                optional($version->created_at)->toDateTimeString(),
            ])->all()
        );

        return self::SUCCESS;
    }
}
