<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create([
            'name' => 'Makanan',
            'slug' => 'makanan-delivery',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Ayam Bakar Delivery',
            'slug' => 'ayam-bakar-delivery',
            'price' => 30000,
            'is_active' => true,
            'sort_order' => 1,
            'firestore_id' => 'delivery-product',
        ]);
    }

    public function test_delivery_checkout_requires_phone_and_address(): void
    {
        $this->from(route('checkout.index'))->post(route('checkout.store'), [
            'customer_name' => 'Salsa',
            'is_delivery' => '1',
            'items' => [[
                'firestore_id' => $this->product->firestore_id,
                'qty' => 1,
            ]],
        ])->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors(['customer_phone', 'delivery_address']);
    }

    public function test_delivery_is_persisted_without_fake_fee_and_cash_is_rejected(): void
    {
        $this->post(route('checkout.store'), [
            'customer_name' => 'Salsa',
            'customer_phone' => '0812-3456-7890',
            'is_delivery' => '1',
            'delivery_address' => 'Jl. Merdeka No. 10',
            'delivery_address_detail' => 'Pagar hitam',
            'delivery_note' => 'Telepon jika sampai',
            'items' => [[
                'firestore_id' => $this->product->firestore_id,
                'qty' => 2,
            ]],
        ])->assertRedirect();

        $order = Order::where('customer_name', 'Salsa')->firstOrFail();
        $this->assertTrue($order->is_delivery);
        $this->assertSame('delivery', $order->order_type);
        $this->assertSame('6281234567890', $order->customer_phone);
        $this->assertNull($order->delivery_fee);
        $this->assertSame('pending', $order->delivery_fee_status);
        $this->assertSame(60000, (int) $order->total);

        $this->post(route('checkout.payment.select', $order->order_code), [
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('payment_method');

        $this->post(route('checkout.payment.select', $order->order_code), [
            'payment_method' => 'bank_transfer',
        ])->assertRedirect(route('order.show', $order->order_code));

        $this->getJson("/api/orders/code/{$order->order_code}")
            ->assertOk()
            ->assertJsonPath('data.is_delivery', true)
            ->assertJsonPath('data.orderType', 'delivery')
            ->assertJsonPath('data.deliveryFee', null)
            ->assertJsonPath('data.delivery_fee_status', 'pending');
    }
}
