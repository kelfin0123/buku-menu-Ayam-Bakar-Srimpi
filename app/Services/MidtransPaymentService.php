<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Midtrans\Config;
use Midtrans\Snap;
use RuntimeException;

class MidtransPaymentService
{
    public function __construct(
        private readonly MidtransConfigService $configService,
    ) {}

    public function createSnapToken(Order $order): string
    {
        $credentials = $this->configService->required();
        Config::$serverKey = $credentials['server_key'];
        Config::$clientKey = $credentials['client_key'];
        Config::$isProduction = $credentials['is_production'];
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $payload = [
            'transaction_details' => [
                'order_id' => $order->order_code,
                'gross_amount' => $order->total,
            ],
            'customer_details' => [
                'first_name' => $order->customer_name,
                'phone' => $order->customer_phone ?? '',
            ],
            'item_details' => $order->items->map(fn ($item) => [
                'id' => $item->product_id,
                'name' => $item->product_name,
                'price' => $item->price,
                'quantity' => $item->qty,
            ])->toArray(),
            'enabled_payments' => ['qris'],
            'expiry' => ['unit' => 'minutes', 'duration' => 5],
        ];

        try {
            $snapToken = Snap::getSnapToken($payload);
        } catch (\Throwable) {
            throw new RuntimeException(
                'Pembayaran tidak dapat diproses. Periksa konfigurasi Midtrans.',
            );
        }

        $order->update([
            'midtrans_snap_token' => $snapToken,
            'midtrans_order_id' => $order->order_code,
        ]);

        return $snapToken;
    }

    public function handleNotification(array $notification): Order
    {
        $order = Order::where('order_code', $notification['order_id'])->firstOrFail();
        $status = $notification['transaction_status'];
        $fraudStatus = $notification['fraud_status'] ?? null;

        if ($status === 'settlement' ||
            ($status === 'capture' && $fraudStatus === 'accept')) {
            $order->payment_status = Order::PAYMENT_STATUS_PAID;
            $order->status = Order::STATUS_NEW_ORDER;
        } elseif (in_array($status, ['cancel', 'deny'], true)) {
            $order->payment_status = Order::PAYMENT_STATUS_FAILED;
            $order->status = Order::STATUS_CANCELLED;
        } elseif ($status === 'expire') {
            $order->payment_status = Order::PAYMENT_STATUS_FAILED;
            $order->status = Order::STATUS_EXPIRED;
        } elseif ($status === 'pending' ||
            ($status === 'capture' && $fraudStatus === 'challenge')) {
            $order->payment_status = Order::PAYMENT_STATUS_PENDING;
        }
        $order->save();

        return $order;
    }

    public function chargePos(
        string $paymentType,
        string $orderId,
        int $amount,
        ?string $phoneNumber = null,
    ): array {
        $credentials = $this->configService->required();
        $payload = [
            'payment_type' => $paymentType,
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
        ];

        if ($paymentType === 'qris') {
            $payload['qris'] = ['acquirer' => 'gopay'];
        } elseif ($paymentType === 'gopay') {
            $payload['gopay'] = ['enable_callback' => false];
        } elseif ($paymentType === 'ovo') {
            $payload['customer_details'] = ['phone' => $phoneNumber];
        }

        $response = Http::withBasicAuth($credentials['server_key'], '')
            ->acceptJson()
            ->timeout(15)
            ->post($this->coreApiUrl($credentials).'/v2/charge', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Midtrans menolak permintaan pembayaran.');
        }

        $data = $response->json();
        $actions = collect($data['actions'] ?? []);

        return [
            'transaction_id' => (string) ($data['transaction_id'] ?? ''),
            'expire_time' => (string) ($data['expiry_time'] ?? ''),
            'qr_string' => (string) ($data['qr_string'] ?? ''),
            'qr_url' => (string) data_get(
                $actions->firstWhere('name', 'generate-qr-code'),
                'url',
                '',
            ),
            'deeplink' => (string) data_get(
                $actions->firstWhere('name', 'deeplink-redirect'),
                'url',
                '',
            ),
        ];
    }

    public function posTransactionStatus(string $orderId): string
    {
        $credentials = $this->configService->required();
        $response = Http::withBasicAuth($credentials['server_key'], '')
            ->acceptJson()
            ->timeout(10)
            ->get($this->coreApiUrl($credentials).'/v2/'.rawurlencode($orderId).'/status');

        if (! $response->successful()) {
            throw new RuntimeException('Status pembayaran tidak dapat diperiksa.');
        }

        return (string) ($response->json('transaction_status') ?? 'unknown');
    }

    private function coreApiUrl(array $credentials): string
    {
        return $credentials['is_production']
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }
}
