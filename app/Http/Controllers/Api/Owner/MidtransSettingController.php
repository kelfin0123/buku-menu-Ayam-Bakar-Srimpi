<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Services\MidtransConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MidtransSettingController extends Controller
{
    public function __construct(
        private readonly MidtransConfigService $configService,
    ) {}

    public function show(): JsonResponse
    {
        $status = $this->configService->maskedStatus();
        $setting = PaymentSetting::where('provider', 'midtrans')->first();

        return response()->json([
            ...$status,
            'last_tested_at' => $setting?->last_tested_at?->toISOString(),
            'last_test_status' => $setting?->last_test_status,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $existing = PaymentSetting::where('provider', 'midtrans')->first();
        $validated = $request->validate([
            'server_key' => [
                Rule::requiredIf(! $existing),
                'nullable',
                'string',
                'min:12',
                'max:255',
            ],
            'client_key' => [
                Rule::requiredIf(! $existing),
                'nullable',
                'string',
                'min:12',
                'max:255',
            ],
            'environment' => 'required|in:sandbox,production',
        ]);

        $values = [
            'environment' => $validated['environment'],
            'is_active' => true,
        ];
        if (filled($validated['server_key'] ?? null)) {
            $values['server_key_encrypted'] = $validated['server_key'];
        }
        if (filled($validated['client_key'] ?? null)) {
            $values['client_key_encrypted'] = $validated['client_key'];
        }

        $setting = PaymentSetting::updateOrCreate(
            ['provider' => 'midtrans'],
            $values,
        );
        Log::info('Midtrans configuration changed', [
            'actor_uid' => $request->attributes->get('firebase_uid'),
            'environment' => $setting->environment,
            'action' => $existing ? 'update' : 'create',
        ]);

        return response()->json([
            'message' => 'Konfigurasi Midtrans berhasil disimpan.',
            ...$this->configService->maskedStatus(),
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $config = $this->configService->required();
        $baseUrl = $config['is_production']
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
        $response = Http::withBasicAuth($config['server_key'], '')
            ->acceptJson()
            ->timeout(10)
            ->get($baseUrl.'/v2/codex-credential-check/status');
        $valid = ! in_array($response->status(), [401, 403], true);

        PaymentSetting::where('provider', 'midtrans')->update([
            'last_tested_at' => now(),
            'last_test_status' => $valid ? 'success' : 'failed',
        ]);
        Log::info('Midtrans configuration tested', [
            'actor_uid' => $request->attributes->get('firebase_uid'),
            'environment' => $config['environment'],
            'result' => $valid ? 'success' : 'failed',
        ]);

        return response()->json([
            'success' => $valid,
            'message' => $valid
                ? "Kredensial {$config['environment']} Midtrans valid."
                : 'Kredensial Midtrans tidak valid.',
        ], $valid ? 200 : 422);
    }

    public function destroy(Request $request): JsonResponse
    {
        PaymentSetting::where('provider', 'midtrans')->delete();
        Log::info('Midtrans configuration changed', [
            'actor_uid' => $request->attributes->get('firebase_uid'),
            'action' => 'delete',
        ]);

        return response()->json([
            'message' => 'Konfigurasi Midtrans dihapus.',
            ...$this->configService->maskedStatus(),
        ]);
    }
}
