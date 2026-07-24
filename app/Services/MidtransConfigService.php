<?php

namespace App\Services;

use App\Models\PaymentSetting;
use RuntimeException;

class MidtransConfigService
{
    public function active(): ?array
    {
        $setting = PaymentSetting::query()
            ->where('provider', 'midtrans')
            ->where('is_active', true)
            ->first();

        if ($setting) {
            return [
                'server_key' => $setting->server_key_encrypted,
                'client_key' => $setting->client_key_encrypted,
                'environment' => $setting->environment,
                'is_production' => $setting->environment === 'production',
                'source' => 'database',
            ];
        }

        $serverKey = config('midtrans.server_key');
        $clientKey = config('midtrans.client_key');
        if (filled($serverKey) && filled($clientKey)) {
            return [
                'server_key' => $serverKey,
                'client_key' => $clientKey,
                'environment' => config('midtrans.is_production') ? 'production' : 'sandbox',
                'is_production' => (bool) config('midtrans.is_production'),
                'source' => 'environment',
            ];
        }

        return null;
    }

    public function required(): array
    {
        return $this->active() ?? throw new RuntimeException(
            'Midtrans belum dikonfigurasi. Hubungi Owner.',
        );
    }

    public function maskedStatus(): array
    {
        $config = $this->active();
        if (! $config) {
            return [
                'configured' => false,
                'environment' => 'sandbox',
                'server_key_masked' => null,
                'client_key_masked' => null,
                'source' => null,
            ];
        }

        return [
            'configured' => true,
            'environment' => $config['environment'],
            'server_key_masked' => $this->mask($config['server_key']),
            'client_key_masked' => $this->mask($config['client_key']),
            'source' => $config['source'],
        ];
    }

    private function mask(string $key): string
    {
        $prefixLength = min(14, max(4, strlen($key) - 8));

        return substr($key, 0, $prefixLength).'****'.substr($key, -4);
    }
}
