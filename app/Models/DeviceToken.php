<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $fillable = [
        'user_id', 'store_id', 'token', 'platform', 'device_name', 'role',
        'is_active', 'sound_enabled', 'vibration_enabled', 'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sound_enabled' => 'boolean',
        'vibration_enabled' => 'boolean',
        'last_used_at' => 'datetime',
    ];
}
