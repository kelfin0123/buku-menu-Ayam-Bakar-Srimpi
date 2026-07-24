<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireFirebaseOwner;
use App\Http\Middleware\RequireFirebaseUser;
use App\Models\PaymentSetting;
use App\Services\MidtransConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransSettingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_owner_settings(): void
    {
        $this->getJson('/api/owner/payment-settings/midtrans')
            ->assertUnauthorized();
    }

    public function test_owner_can_store_encrypted_keys_and_api_only_returns_masking(): void
    {
        $response = $this->withoutMiddleware(RequireFirebaseOwner::class)
            ->putJson('/api/owner/payment-settings/midtrans', [
                'server_key' => 'SB-Mid-server-super-secret-abcd',
                'client_key' => 'SB-Mid-client-super-secret-wxyz',
                'environment' => 'sandbox',
            ])
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('environment', 'sandbox');

        $response->assertJsonMissing(['server_key' => 'SB-Mid-server-super-secret-abcd']);
        $this->assertStringNotContainsString(
            'super-secret',
            $response->getContent(),
        );

        $raw = \DB::table('payment_settings')->first();
        $this->assertNotSame(
            'SB-Mid-server-super-secret-abcd',
            $raw->server_key_encrypted,
        );
        $this->assertSame(
            'SB-Mid-server-super-secret-abcd',
            PaymentSetting::firstOrFail()->server_key_encrypted,
        );
    }

    public function test_empty_keys_on_edit_keep_existing_encrypted_values(): void
    {
        PaymentSetting::create([
            'provider' => 'midtrans',
            'environment' => 'sandbox',
            'server_key_encrypted' => 'SB-Mid-server-existing-abcd',
            'client_key_encrypted' => 'SB-Mid-client-existing-wxyz',
            'is_active' => true,
        ]);

        $this->withoutMiddleware(RequireFirebaseOwner::class)
            ->putJson('/api/owner/payment-settings/midtrans', [
                'server_key' => '',
                'client_key' => '',
                'environment' => 'production',
            ])->assertOk();

        $setting = PaymentSetting::firstOrFail();
        $this->assertSame('SB-Mid-server-existing-abcd', $setting->server_key_encrypted);
        $this->assertSame('production', $setting->environment);
    }

    public function test_connection_result_is_saved_without_exposing_credentials(): void
    {
        PaymentSetting::create([
            'provider' => 'midtrans',
            'environment' => 'sandbox',
            'server_key_encrypted' => 'SB-Mid-server-existing-abcd',
            'client_key_encrypted' => 'SB-Mid-client-existing-wxyz',
            'is_active' => true,
        ]);
        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response([], 404),
        ]);

        $this->withoutMiddleware(RequireFirebaseOwner::class)
            ->postJson('/api/owner/payment-settings/midtrans/test')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(
            'success',
            PaymentSetting::firstOrFail()->last_test_status,
        );
    }

    public function test_invalid_connection_is_rejected_without_exposing_credentials(): void
    {
        PaymentSetting::create([
            'provider' => 'midtrans',
            'environment' => 'sandbox',
            'server_key_encrypted' => 'SB-Mid-server-invalid-secret',
            'client_key_encrypted' => 'SB-Mid-client-invalid-public',
            'is_active' => true,
        ]);
        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response([], 401),
        ]);

        $response = $this->withoutMiddleware(RequireFirebaseOwner::class)
            ->postJson('/api/owner/payment-settings/midtrans/test')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame('failed', PaymentSetting::firstOrFail()->last_test_status);
        $this->assertStringNotContainsString('invalid-secret', $response->getContent());
    }

    public function test_environment_variables_are_used_when_database_is_empty(): void
    {
        config()->set('midtrans.server_key', 'SB-Mid-server-env-abcd');
        config()->set('midtrans.client_key', 'SB-Mid-client-env-wxyz');
        config()->set('midtrans.is_production', false);

        $config = app(MidtransConfigService::class)->active();

        $this->assertSame('environment', $config['source']);
        $this->assertSame('sandbox', $config['environment']);
        $this->assertSame('SB-Mid-server-env-abcd', $config['server_key']);
    }

    public function test_pos_payment_proxy_requires_firebase_authentication(): void
    {
        $this->postJson('/api/payment/pos/charge', [
            'payment_type' => 'qris',
            'order_id' => 'POS-SECURITY-1',
            'amount' => 10000,
        ])->assertUnauthorized();
    }

    public function test_pos_payment_is_proxied_without_exposing_server_key(): void
    {
        PaymentSetting::create([
            'provider' => 'midtrans',
            'environment' => 'sandbox',
            'server_key_encrypted' => 'SB-Mid-server-proxy-secret',
            'client_key_encrypted' => 'SB-Mid-client-proxy-public',
            'is_active' => true,
        ]);
        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'transaction_id' => 'transaction-123',
                'qr_string' => 'safe-qr-payload',
                'expiry_time' => '2026-07-24 20:00:00',
                'actions' => [[
                    'name' => 'generate-qr-code',
                    'url' => 'https://example.test/qr.png',
                ]],
            ], 201),
        ]);

        $response = $this->withoutMiddleware(RequireFirebaseUser::class)
            ->postJson('/api/payment/pos/charge', [
                'payment_type' => 'qris',
                'order_id' => 'POS-SECURITY-2',
                'amount' => 15000,
            ])
            ->assertOk()
            ->assertJsonPath('data.transaction_id', 'transaction-123')
            ->assertJsonPath('data.qr_url', 'https://example.test/qr.png');

        $this->assertStringNotContainsString('proxy-secret', $response->getContent());
        Http::assertSent(fn ($request) => $request->url() ===
            'https://api.sandbox.midtrans.com/v2/charge' &&
            $request['transaction_details']['gross_amount'] === 15000);
    }
}
