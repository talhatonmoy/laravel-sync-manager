<?php

namespace DeployCar\LaravelSyncManager\Services;

use Illuminate\Support\Facades\Schema;

class SchemaReadiness
{
    public function hasOperations(): bool
    {
        return Schema::hasTable('sync_operations');
    }

    public function hasTargets(): bool
    {
        return Schema::hasTable('sync_targets');
    }

    public function hasSettings(): bool
    {
        return Schema::hasTable('sync_settings');
    }

    public function migrationMessage(): string
    {
        return 'DeployCar needs its latest package migrations. Run php artisan migrate in this app to enable operations and dashboard-managed configuration.';
    }
}
