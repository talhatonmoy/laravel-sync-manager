<?php

namespace DeployCar\LaravelSyncManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    public $timestamps = false;

    protected $table = 'sync_logs';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(SyncVersion::class, 'sync_version_id');
    }
}
