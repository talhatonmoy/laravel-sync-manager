<?php

namespace DeployCar\LaravelSyncManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOperation extends Model
{
    protected $table = 'sync_operations';

    protected $guarded = [];

    protected $casts = [
        'result_payload' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(SyncVersion::class, 'sync_version_id');
    }
}
