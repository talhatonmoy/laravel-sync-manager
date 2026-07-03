<?php

namespace DeployCar\LaravelSyncManager\Models;

use Illuminate\Database\Eloquent\Model;

class SyncTargetState extends Model
{
    protected $table = 'sync_target_states';

    protected $guarded = [];

    protected $casts = [
        'modified_at' => 'datetime',
    ];
}
