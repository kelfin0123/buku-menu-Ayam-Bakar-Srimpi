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
        'expires_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'finished_at' => 'datetime',
        'rejected_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Order Status Constants
    const STATUS_WAITING_PAYMENT = 'waiting_payment';
    const STATUS_NEW_ORDER = 'new_order';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY = 'ready';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    // Payment Status Constants
    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_FAILED = 'failed';

    // Payment Method Constants
    const PAYMENT_METHOD_CASH = 'cash';
    const PAYMENT_METHOD_QRIS = 'qris';

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Generate kode order unik: ABS-YYYYMMDD-XXXX */
    public static function generateOrderCode(): string
    {
        return 'ABS-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
    }

    /** Check if order is expired */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /** Mark order as expired and restore stock */
    public function markAsExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->payment_status = self::PAYMENT_STATUS_FAILED;
        $this->save();

        // Restore stock
        foreach ($this->items as $item) {
            if ($item->product) {
                $item->product->increment('stock', $item->qty);
            }
        }
    }

    /** Scope for incoming orders (new orders waiting for acceptance) */
    public function scopeIncoming($query)
    {
        return $query->where(function ($query) {
            $query->where(function ($paid) {
                $paid->whereIn('status', [self::STATUS_NEW_ORDER, self::STATUS_WAITING_PAYMENT])
                    ->where('payment_status', self::PAYMENT_STATUS_PAID);
            })->orWhere(function ($cash) {
                $cash->where('status', self::STATUS_NEW_ORDER)
                    ->where('payment_method', self::PAYMENT_METHOD_CASH)
                    ->where('payment_status', self::PAYMENT_STATUS_PENDING);
            });
        })
            ->orderByDesc('created_at');
    }

    /** Scope for active orders (not cancelled, expired, or completed) */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
            self::STATUS_COMPLETED
        ]);
    }
}
