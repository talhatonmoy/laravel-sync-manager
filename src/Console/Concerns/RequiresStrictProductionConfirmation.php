<?php

namespace DeployCar\LaravelSyncManager\Console\Concerns;

trait RequiresStrictProductionConfirmation
{
    /**
     * Confirm before proceeding with the action if in production.
     *
     * @param  string  $warning
     * @return bool
     */
    public function confirmStrictly(string $warning = 'Application In Production!'): bool
    {
        if (! app()->environment('production')) {
            return true;
        }

        if ($this->hasOption('force') && $this->option('force')) {
            return true;
        }

        $this->components->warn($warning);

        $confirmation = $this->ask('Please type "I know what I am doing" to confirm running this destructive command');

        if ($confirmation !== 'I know what I am doing') {
            $this->components->error('Command aborted.');

            return false;
        }

        return true;
    }
}
