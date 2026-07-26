<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $fillable = [
        'employee_id', 'user_id', 'store_id', 'fcm_token', 'platform', 'device_name', 'role',
        'is_active', 'sound_enabled', 'vibration_enabled', 'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sound_enabled' => 'boolean',
        'vibration_enabled' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
