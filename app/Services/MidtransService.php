<?php

namespace App\Services;

use App\Models\Order;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct(MidtransConfigService $configService)
    {
        $credentials = $configService->required();
        Config::$serverKey = $credentials['server_key'];
        Config::$clientKey = $credentials['client_key'];
        Config::$isProduction = $credentials['is_production'];
        Config::$isSanitized = config('midtrans.is_sanitized', true);
        Config::$is3ds = config('midtrans.is_3ds', true);
    }

    /**
     * Create Snap token for QRIS payment
     */
    public function createSnapToken(Order $order): string
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $order->order_code,
                'gross_amount' => $order->total,
            ],
            'customer_details' => [
                'first_name' => $order->customer_name,
                'phone' => $order->customer_phone ?? '',
            ],
            'item_details' => $order->items->map(function ($item) {
                return [
                    'id' => $item->product_id,
                    'name' => $item->product_name,
                    'price' => $item->price,
                    'quantity' => $item->qty,
                ];
            })->toArray(),
            'enabled_payments' => ['qris'],
            'expiry' => [
                'unit' => 'minutes',
                'duration' => 5,
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($payload);

            // Update order with Midtrans data
            $order->update([
                'midtrans_snap_token' => $snapToken,
                'midtrans_order_id' => $order->order_code,
            ]);

            return $snapToken;
        } catch (\Exception $e) {
            throw new \Exception('Gagal membuat Snap token: '.$e->getMessage());
        }
    }

    /**
     * Handle Midtrans notification callback
     */
    public function handleNotification(array $notification): Order
    {
        $orderCode = $notification['order_id'];
        $transactionStatus = $notification['transaction_status'];
        $fraudStatus = $notification['fraud_status'] ?? null;

        $order = Order::where('order_code', $orderCode)->firstOrFail();

        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'challenge') {
                $order->payment_status = Order::PAYMENT_STATUS_PENDING;
            } elseif ($fraudStatus == 'accept') {
                $order->payment_status = Order::PAYMENT_STATUS_PAID;
                $order->status = Order::STATUS_NEW_ORDER;
            }
        } elseif ($transactionStatus == 'settlement') {
            $order->payment_status = Order::PAYMENT_STATUS_PAID;
            $order->status = Order::STATUS_NEW_ORDER;
        } elseif ($transactionStatus == 'cancel') {
            $order->payment_status = Order::PAYMENT_STATUS_FAILED;
            $order->status = Order::STATUS_CANCELLED;
        } elseif ($transactionStatus == 'deny') {
            $order->payment_status = Order::PAYMENT_STATUS_FAILED;
            $order->status = Order::STATUS_CANCELLED;
        } elseif ($transactionStatus == 'expire') {
            $order->payment_status = Order::PAYMENT_STATUS_FAILED;
            $order->status = Order::STATUS_EXPIRED;
        } elseif ($transactionStatus == 'pending') {
            $order->payment_status = Order::PAYMENT_STATUS_PENDING;
        }

        $order->save();

        return $order;
    }
}
