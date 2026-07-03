<?php

namespace DeployCar\LaravelSyncManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncFile extends Model
{
    protected $table = 'sync_files';

    protected $guarded = [];

    protected $casts = [
        'modified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(SyncVersion::class, 'sync_version_id');
    }
}
