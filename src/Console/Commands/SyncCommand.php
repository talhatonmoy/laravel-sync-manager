<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\CommandUX;
use DeployCar\LaravelSyncManager\Services\TargetResolver;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    use CommandUX;

    protected $signature = 'sync {action?} {target?} {--all} {--no-delete} {--force} {--undo} {--dry-run} {--status=} {--keep-versions=}';

    protected $description = 'Sync files between local and production (interactive menu if no action provided).';

    public function handle(TargetResolver $targetResolver): int
    {
        $action = $this->argument('action');

        // If action provided, route directly (skip menu).
        if ($action) {
            return $this->executeAction($action);
        }

        // Show interactive menu.
        $choices = [
            'preview' => 'Preview changes before syncing',
            'send' => 'Send changes to production',
            'dry-run' => 'Dry run (detailed preview)',
            'pull' => 'Pull production files to local',
            'history' => 'View sync history',
            'rollback' => 'Rollback to previous sync state',
            'restore-local' => 'Restore local files from backup',
            'scan' => 'Scan local files and show manifest',
            'prune' => 'Prune old objects from store',
        ];

        $selected = $this->choice('What would you like to do?', array_values($choices));
        $action = array_search($selected, $choices);

        return $this->executeAction($action);
    }

    private function executeAction(string $action): int
    {
        $target = $this->argument('target');
        $cmdArgs = [];

        if ($target) {
            $cmdArgs['target'] = $target;
        }

        $cmdOptions = [];
        if ($this->option('all')) {
            $cmdOptions['--all'] = true;
        }
        if ($this->option('no-delete')) {
            $cmdOptions['--no-delete'] = true;
        }
        if ($this->option('force')) {
            $cmdOptions['--force'] = true;
        }
        if ($this->option('undo')) {
            $cmdOptions['--undo'] = true;
        }
        if ($this->option('dry-run')) {
            $cmdOptions['--dry-run'] = true;
        }
        if ($this->option('status')) {
            $cmdOptions['--status'] = $this->option('status');
        }
        if ($this->option('keep-versions')) {
            $cmdOptions['--keep-versions'] = $this->option('keep-versions');
        }

        $mapping = [
            'preview' => 'sync:preview',
            'send' => 'sync:send',
            'dry-run' => 'sync:dry-run',
            'pull' => 'sync:pull',
            'history' => 'sync:history',
            'rollback' => 'sync:rollback',
            'restore-local' => 'sync:restore-local',
            'scan' => 'sync:scan',
            'prune' => 'sync:prune-objects',
        ];

        if (! isset($mapping[$action])) {
            $this->error("Unknown action: {$action}");

            return self::FAILURE;
        }

        return $this->call($mapping[$action], $cmdArgs + $cmdOptions);
    }
}
