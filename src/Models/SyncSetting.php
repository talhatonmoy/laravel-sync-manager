<?php

namespace DeployCar\LaravelSyncManager\Models;

use Illuminate\Database\Eloquent\Model;

class SyncSetting extends Model
{
    protected $table = 'sync_settings';

    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
    ];
}
