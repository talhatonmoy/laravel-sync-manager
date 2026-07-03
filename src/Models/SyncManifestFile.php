<?php

namespace DeployCar\LaravelSyncManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncManifestFile extends Model
{
    protected $table = 'sync_manifest_files';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'modified_at' => 'datetime',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(SyncManifest::class, 'sync_manifest_id');
    }
}
