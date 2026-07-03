<?php

namespace DeployCar\LaravelSyncManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncVersion extends Model
{
    protected $table = 'sync_versions';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'summary' => 'array',
        'completed_at' => 'datetime',
        'applied_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(SyncFile::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }
}
