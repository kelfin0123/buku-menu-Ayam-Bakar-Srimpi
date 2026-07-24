<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Order;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseMessagingService
{
    public function sendNewOrder(Order $order): int
    {
        $accessToken = $this->accessToken();
        $projectId = (string) config('firebase.project_id');
        $tokens = DeviceToken::query()
            ->where('is_active', true)
            ->whereIn('role', ['employee', 'kasir', 'owner'])
            ->get();
        $sent = 0;

        foreach ($tokens as $device) {
            $response = Http::acceptJson()
                ->withToken($accessToken)
                ->timeout(15)
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                    ['message' => $this->payload($order, $device)],
                );

            if ($response->successful()) {
                $device->update(['last_used_at' => now()]);
                $sent++;

                continue;
            }

            if (in_array($response->status(), [400, 404], true)) {
                $error = (string) $response->json('error.details.0.errorCode');
                if (in_array($error, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                    $device->update(['is_active' => false]);
                }
            }
        }

        return $sent;
    }

    public function payload(Order $order, DeviceToken $device): array
    {
        $customer = trim((string) $order->customer_name);
        $rupiah = 'Rp'.number_format((int) $order->total, 0, ',', '.');
        $typeLabel = $order->is_delivery ? 'Pesan Antar ke Rumah' : 'Ambil Sendiri';
        $body = $customer !== ''
            ? "{$order->order_code} • {$customer}\nTotal {$rupiah}\n{$typeLabel}"
            : "Pesanan baru senilai {$rupiah}\n{$typeLabel}";

        $channelId = match ([$device->sound_enabled, $device->vibration_enabled]) {
            [true, true] => 'new_order_channel_v1',
            [true, false] => 'new_order_sound_only_channel_v1',
            [false, true] => 'new_order_vibrate_only_channel_v1',
            default => 'new_order_silent_channel_v1',
        };
        $androidNotification = [
            'channel_id' => $channelId,
            'notification_priority' => 'PRIORITY_MAX',
            'visibility' => 'PUBLIC',
            'default_vibrate_timings' => $device->vibration_enabled,
        ];
        if ($device->sound_enabled) {
            $androidNotification['sound'] = 'new_order';
        }
        $aps = ['badge' => 1];
        if ($device->sound_enabled) {
            $aps['sound'] = 'new_order.wav';
        }

        return [
            'token' => $device->token,
            'notification' => [
                'title' => 'Pesanan Baru Masuk 🔔',
                'body' => $body,
            ],
            'data' => [
                'type' => 'new_order',
                'order_id' => (string) $order->id,
                'order_code' => (string) $order->order_code,
                'order_type' => (string) ($order->order_type ?: 'pickup'),
                'customer_name' => $customer,
                'total' => (string) ((int) $order->total),
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            'android' => [
                'priority' => 'high',
                'notification' => $androidNotification,
            ],
            'apns' => [
                'payload' => [
                    'aps' => $aps,
                ],
            ],
        ];
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();
        $token = (new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/firebase.messaging'],
            $credentials,
        ))->fetchAuthToken()['access_token'] ?? null;

        return $token ?: throw new RuntimeException('Firebase access token tidak tersedia.');
    }

    private function credentials(): array
    {
        if ($encoded = config('firebase.credentials_base64')) {
            $decoded = base64_decode((string) $encoded, true);
            $json = $decoded === false ? null : json_decode($decoded, true);
            if (is_array($json)) {
                return $json;
            }
        }
        if ($path = config('firebase.credentials')) {
            $json = is_readable($path)
                ? json_decode((string) file_get_contents($path), true)
                : null;
            if (is_array($json)) {
                return $json;
            }
        }

        $email = (string) config('firebase.client_email', '');
        $key = (string) config('firebase.private_key', '');
        if ($email === '' || $key === '') {
            throw new RuntimeException('Firebase Admin belum dikonfigurasi.');
        }

        return [
            'type' => 'service_account',
            'project_id' => config('firebase.project_id'),
            'private_key' => $key,
            'client_email' => $email,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ];
    }
}
