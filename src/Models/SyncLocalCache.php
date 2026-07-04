<?php

namespace DeployCar\LaravelSyncManager\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLocalCache extends Model
{
    protected $table = 'sync_local_cache';

    protected $guarded = [];

    protected $casts = [
        'mtime' => 'integer',
        'size' => 'integer',
    ];
}
