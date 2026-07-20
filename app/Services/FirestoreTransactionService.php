<?php

namespace App\Services;

use App\Models\Order;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FirestoreTransactionService
{
    public function syncCompletedOrder(Order $order, string $employeeUid): bool
    {
        try {
            $order->loadMissing('items.product');
            $projectId = (string) config('firebase.project_id');
            $credentials = $this->credentials($projectId);
            if ($credentials === null) {
                throw new RuntimeException('Firebase service account wajib dikonfigurasi.');
            }

            $token = (new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $credentials,
            ))->fetchAuthToken()['access_token'] ?? null;
            if (! $token) {
                throw new RuntimeException('Gagal memperoleh access token Firebase.');
            }

            // ID stabil membuat Complete aman diulang tanpa transaksi ganda.
            $documentId = $this->documentId($order);
            $url = sprintf(
                'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/transactions/%s',
                rawurlencode($projectId),
                rawurlencode($documentId),
            );

            Http::acceptJson()->withToken($token)->timeout(20)->retry(2, 250)
                ->patch($url, ['fields' => $this->encodeMap($this->payload($order, $employeeUid))])
                ->throw();

            Log::info('Completed web order synchronized as Firestore transaction', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'transaction_document' => $documentId,
                'employee_uid' => $employeeUid,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed synchronizing completed order as Firestore transaction', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return false;
        }
    }

    public function documentId(Order $order): string
    {
        return 'web_order_'.$order->order_code;
    }

    private function payload(Order $order, string $employeeUid): array
    {
        $completedAt = $order->finished_at ?? now();

        return [
            'userId' => $employeeUid,
            'employeeId' => $employeeUid,
            'branchId' => null,
            'cashierName' => null,
            'customerName' => $order->customer_name,
            'transactionType' => 'sale',
            'amount' => (int) $order->total,
            'type' => 'income',
            'createdAt' => $completedAt,
            'updatedAt' => now(),
            'description' => 'Pesanan website '.$order->order_code,
            'paymentMethod' => $order->payment_method,
            'items' => $order->items->map(fn ($item) => [
                'productId' => $item->product?->firestore_id ?? (string) $item->product_id,
                'productName' => $item->product_name,
                'price' => (int) $item->price,
                'quantity' => (int) $item->qty,
                'category' => null,
                'costPrice' => 0,
            ])->values()->all(),
            'discount' => 0,
            'tax' => 0,
            'subtotal' => (int) $order->subtotal,
            'status' => 'paid',
            'orderId' => $order->order_code,
            'orderCode' => $order->order_code,
            'source' => 'web',
            'totalHPP' => 0,
            'grossProfit' => (int) $order->total,
            'netProfit' => (int) $order->total,
        ];
    }

    private function encodeMap(array $data): array
    {
        return collect($data)->map(fn ($value) => $this->encodeValue($value))->all();
    }

    private function encodeValue(mixed $value): array
    {
        if ($value === null) {
            return ['nullValue' => null];
        }
        if ($value instanceof \DateTimeInterface) {
            return ['timestampValue' => $value->format(DATE_RFC3339_EXTENDED)];
        }
        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }
        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }
        if (is_float($value)) {
            return ['doubleValue' => $value];
        }
        if (is_array($value) && array_is_list($value)) {
            return ['arrayValue' => ['values' => array_map(fn ($item) => $this->encodeValue($item), $value)]];
        }
        if (is_array($value)) {
            return ['mapValue' => ['fields' => $this->encodeMap($value)]];
        }

        return ['stringValue' => (string) $value];
    }

    private function credentials(string $projectId): ?array
    {
        if ($encoded = config('firebase.credentials_base64')) {
            $decoded = base64_decode((string) $encoded, true);
            $json = $decoded === false ? null : json_decode($decoded, true);
            if (! is_array($json)) {
                throw new RuntimeException('FIREBASE_CREDENTIALS_BASE64 tidak valid.');
            }

            return $json;
        }
        if ($path = config('firebase.credentials')) {
            if (! is_readable($path)) {
                throw new RuntimeException("Firebase credential tidak terbaca: {$path}");
            }
            $json = json_decode((string) file_get_contents($path), true);
            if (! is_array($json)) {
                throw new RuntimeException('Firebase credential JSON tidak valid.');
            }

            return $json;
        }
        $email = (string) config('firebase.client_email', '');
        $key = (string) config('firebase.private_key', '');
        if ($email === '' && $key === '') {
            return null;
        }
        if ($email === '' || $key === '') {
            throw new RuntimeException('Firebase email dan private key harus lengkap.');
        }

        return array_filter([
            'type' => 'service_account', 'project_id' => $projectId,
            'private_key_id' => config('firebase.private_key_id'), 'private_key' => $key,
            'client_email' => $email, 'client_id' => config('firebase.client_id'),
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
    }
}
