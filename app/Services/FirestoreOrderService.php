<?php

namespace App\Services;

use App\Models\Order;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FirestoreOrderService
{
    public function sync(Order $order): bool
    {
        Log::info('Firestore order synchronization started', [
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'project_id' => config('firebase.project_id'),
            'collection' => 'orders',
        ]);

        try {
            $order->loadMissing('items.product');
            $projectId = (string) config('firebase.project_id');
            $credentials = $this->credentials($projectId);
            if ($credentials === null) {
                throw new RuntimeException('Firebase service account wajib dikonfigurasi untuk menulis orders.');
            }

            $token = (new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $credentials,
            ))->fetchAuthToken()['access_token'] ?? null;
            if (!$token) {
                throw new RuntimeException('Gagal memperoleh access token Firebase.');
            }

            $url = sprintf(
                'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/orders/%s',
                rawurlencode($projectId),
                rawurlencode($order->order_code),
            );
            Http::acceptJson()->withToken($token)->timeout(20)->retry(2, 250)
                ->patch($url, ['fields' => $this->encodeMap($this->payload($order))])
                ->throw();

            Log::info('Firestore order synchronized', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'status' => $order->status,
                'collection' => 'orders',
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed synchronizing Firestore order', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return false;
        }
    }

    private function payload(Order $order): array
    {
        return [
            'id' => (string) $order->id,
            'sqlId' => $order->id,
            'orderCode' => $order->order_code,
            'customerName' => $order->customer_name,
            'tableNumber' => $order->table_number,
            'source' => 'web',
            'status' => $order->status,
            'paymentMethod' => $order->payment_method,
            'paymentStatus' => $order->payment_status === Order::PAYMENT_STATUS_PENDING ? 'unpaid' : $order->payment_status,
            'subtotal' => (int) $order->subtotal,
            'shippingCost' => (int) $order->shipping_cost,
            'total' => (int) $order->total,
            'employeeId' => $order->employee_id,
            'acceptedAt' => $order->accepted_at,
            'rejectedAt' => $order->rejected_at,
            'finishedAt' => $order->finished_at,
            'expiresAt' => $order->expires_at,
            'createdAt' => $order->created_at,
            'updatedAt' => $order->updated_at,
            'items' => $order->items->map(fn ($item) => [
                'productId' => $item->product?->firestore_id ?? (string) $item->product_id,
                'name' => $item->product_name,
                'price' => (int) $item->price,
                'quantity' => (int) $item->qty,
                'subtotal' => (int) $item->subtotal,
                'imageUrl' => $item->product?->image,
            ])->values()->all(),
        ];
    }

    private function encodeMap(array $data): array
    {
        return collect($data)->map(fn ($value) => $this->encodeValue($value))->all();
    }

    private function encodeValue(mixed $value): array
    {
        if ($value === null) return ['nullValue' => null];
        if ($value instanceof \DateTimeInterface) return ['timestampValue' => $value->format(DATE_RFC3339_EXTENDED)];
        if (is_bool($value)) return ['booleanValue' => $value];
        if (is_int($value)) return ['integerValue' => (string) $value];
        if (is_float($value)) return ['doubleValue' => $value];
        if (is_array($value) && array_is_list($value)) return ['arrayValue' => ['values' => array_map(fn ($item) => $this->encodeValue($item), $value)]];
        if (is_array($value)) return ['mapValue' => ['fields' => $this->encodeMap($value)]];
        return ['stringValue' => (string) $value];
    }

    private function credentials(string $projectId): ?array
    {
        if ($encoded = config('firebase.credentials_base64')) {
            $decoded = base64_decode((string) $encoded, true);
            $json = $decoded === false ? null : json_decode($decoded, true);
            if (!is_array($json)) throw new RuntimeException('FIREBASE_CREDENTIALS_BASE64 tidak valid.');
            return $json;
        }
        if ($path = config('firebase.credentials')) {
            if (!is_readable($path)) throw new RuntimeException("Firebase credential tidak terbaca: {$path}");
            $json = json_decode((string) file_get_contents($path), true);
            if (!is_array($json)) throw new RuntimeException('Firebase credential JSON tidak valid.');
            return $json;
        }
        $email = (string) config('firebase.client_email', '');
        $key = (string) config('firebase.private_key', '');
        if ($email === '' && $key === '') return null;
        if ($email === '' || $key === '') throw new RuntimeException('Firebase email dan private key harus lengkap.');
        return array_filter([
            'type' => 'service_account', 'project_id' => $projectId,
            'private_key_id' => config('firebase.private_key_id'), 'private_key' => $key,
            'client_email' => $email, 'client_id' => config('firebase.client_id'),
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
    }
}
