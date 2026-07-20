<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\FirestoreOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_checkout_persists_required_customer_fields_and_syncs_firestore(): void
    {
        $category = Category::create([
            'name' => 'Makanan',
            'slug' => 'makanan-api',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Ayam API',
            'slug' => 'ayam-api',
            'price' => 25000,
            'is_promo' => false,
            'is_active' => true,
            'sort_order' => 1,
            'firestore_id' => 'firestore-api-product',
        ]);

        $this->mock(FirestoreOrderService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sync')->once()->andReturnTrue();
        });

        $this->postJson('/api/checkout', [
            'customer_name' => 'Customer API',
            'table_number' => 'API-1',
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->firestore_id,
                'qty' => 1,
            ]],
        ])->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Customer API',
            'customer_phone' => '',
            'status' => Order::STATUS_WAITING_PAYMENT,
        ]);
    }

    public function test_pending_accept_reject_and_finish_workflow(): void
    {
        $order = Order::create([
            'order_code' => 'ABS-20260713-0001',
            'customer_name' => 'Budi',
            'customer_phone' => '08123456789',
            'customer_address' => 'Jakarta',
            'subtotal' => 20000,
            'shipping_cost' => 5000,
            'total' => 25000,
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'status' => Order::STATUS_NEW_ORDER,
        ]);

        $response = $this->getJson('/api/orders/incoming');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $order->id]);

        $acceptResponse = $this->postJson("/api/orders/{$order->id}/accept", [
            'employee_id' => 7,
        ]);

        $acceptResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Order::STATUS_PROCESSING);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_PROCESSING,
            'employee_id' => 7,
        ]);

        $finishResponse = $this->postJson("/api/orders/{$order->id}/complete");
        $finishResponse->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_COMPLETED);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $rejectedOrder = Order::create([
            'order_code' => 'ABS-20260713-0002',
            'customer_name' => 'Ani',
            'customer_phone' => '08123456780',
            'customer_address' => 'Bandung',
            'subtotal' => 10000,
            'shipping_cost' => 5000,
            'total' => 15000,
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'status' => Order::STATUS_NEW_ORDER,
        ]);

        $rejectResponse = $this->postJson("/api/orders/{$rejectedOrder->id}/reject", [
            'employee_id' => 8,
            'rejection_reason' => 'Stok habis',
        ]);

        $rejectResponse->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CANCELLED);

        $this->assertDatabaseHas('orders', [
            'id' => $rejectedOrder->id,
            'status' => Order::STATUS_CANCELLED,
            'rejection_reason' => 'Stok habis',
        ]);
    }
}
