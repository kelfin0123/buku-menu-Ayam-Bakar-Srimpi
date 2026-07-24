<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = [
        'provider',
        'environment',
        'server_key_encrypted',
        'client_key_encrypted',
        'is_active',
        'last_tested_at',
        'last_test_status',
    ];

    protected function casts(): array
    {
        return [
            'server_key_encrypted' => 'encrypted',
            'client_key_encrypted' => 'encrypted',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
