<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\CommandUX;
use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use Illuminate\Console\Command;

class PruneObjectsCommand extends Command
{
    use CommandUX;

    protected $signature = 'sync:prune-objects
        {--dry-run : Report what would be removed without actually deleting}';

    protected $description = 'Remove orphaned object blobs from the object store (reference_count <= 0).';

    public function handle(ObjectStoreInterface $objectStore): int
    {
        try {
            if ($this->option('dry-run')) {
                $orphanCount = \DeployCar\LaravelSyncManager\Models\SyncFileObject::query()
                    ->where('reference_count', '<=', 0)
                    ->count();

                $this->line("Dry run: {$orphanCount} orphaned object(s) would be removed.");

                return self::SUCCESS;
            }

            $result = $objectStore->prune();

            $this->line("Pruned {$result['removed']} orphaned object(s), freed {$result['freed_bytes']} bytes.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            return $this->renderError($e);
        }
    }
}
