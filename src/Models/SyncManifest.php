<?php

namespace DeployCar\LaravelSyncManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncManifest extends Model
{
    protected $table = 'sync_manifests';

    protected $guarded = [];

    protected $casts = [
        'summary' => 'array',
        'metadata' => 'array',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(SyncVersion::class, 'sync_version_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(SyncManifestFile::class, 'sync_manifest_id');
    }
}
