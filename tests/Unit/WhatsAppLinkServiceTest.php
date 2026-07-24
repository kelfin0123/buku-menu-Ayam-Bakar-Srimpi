<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\WhatsAppLinkService;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class WhatsAppLinkServiceTest extends TestCase
{
    private WhatsAppLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WhatsAppLinkService;
    }

    public function test_it_normalizes_indonesian_phone_variants(): void
    {
        $this->assertSame('6281931922732', $this->service->normalizePhone('081931922732'));
        $this->assertSame('6281931922732', $this->service->normalizePhone('81931922732'));
        $this->assertSame('6281931922732', $this->service->normalizePhone('+6281931922732'));
        $this->assertSame('6281931922732', $this->service->normalizePhone('62 819-3192-2732'));
    }

    public function test_it_rejects_empty_phone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->normalizePhone('');
    }

    public function test_it_builds_encoded_receipt_url(): void
    {
        $order = new Order([
            'order_code' => 'ABS-001',
            'customer_name' => 'Budi',
            'customer_phone' => '081931922732',
            'subtotal' => 25000,
            'total' => 25000,
            'payment_method' => 'cash',
            'status' => Order::STATUS_COMPLETED,
        ]);
        $order->created_at = Carbon::parse('2026-07-24 12:00:00');
        $order->setRelation('items', collect([
            new OrderItem([
                'product_name' => 'Ayam Bakar',
                'qty' => 1,
                'subtotal' => 25000,
            ]),
        ]));

        $url = $this->service->buildWhatsAppUrl(
            $order,
            'https://example.test/receipt/signed',
        );

        $this->assertStringStartsWith('https://wa.me/6281931922732?text=', $url);
        $this->assertStringContainsString('Ayam%20Bakar', $url);
        $this->assertStringContainsString('receipt%2Fsigned', $url);
    }
}
