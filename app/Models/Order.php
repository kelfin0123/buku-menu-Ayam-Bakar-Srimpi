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
        'subtotal',
        'shipping_cost',
        'total',
        'payment_method',
        'payment_status',
        'midtrans_snap_token',
        'midtrans_order_id',
        'status',
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
