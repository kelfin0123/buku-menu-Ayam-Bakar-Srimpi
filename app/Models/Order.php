<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_code',
        'customer_name',
        'customer_phone',
        'customer_address',
        'table_number',
        'subtotal',
        'shipping_cost',
        'total',
        'payment_method',
        'payment_status',
        'midtrans_snap_token',
        'midtrans_order_id',
        'status',
        'employee_id',
        'accepted_at',
        'finished_at',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'finished_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Generate kode order unik: ABS-YYYYMMDD-XXXX */
    public static function generateOrderCode(): string
    {
        return 'ABS-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
    }
}
