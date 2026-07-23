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
        $this->mock(FirestoreOrderService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sync')->times(2)->andReturnTrue();
            $mock->shouldReceive('syncCompletion')
                ->once()
                ->withArgs(fn (Order $order, array $actor) =>
                    $order->status === Order::STATUS_COMPLETED
                    && $actor['employee_uid'] === 'firebase-employee-7')
                ->andReturnTrue();
        });

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

        $finishResponse = $this->postJson("/api/orders/{$order->id}/complete", [
            'employee_uid' => 'firebase-employee-7',
            'employee_name' => 'Kasir Budi',
            'owner_id' => 'firebase-owner-1',
            'branch_id' => 'branch-jakarta',
        ]);
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

    public function test_complete_returns_retryable_error_when_firestore_transaction_sync_fails(): void
    {
        $order = Order::create([
            'order_code' => 'ABS-20260713-0003',
            'customer_name' => 'Retry Customer',
            'customer_phone' => '08123456781',
            'subtotal' => 12000,
            'shipping_cost' => 0,
            'total' => 12000,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'status' => Order::STATUS_READY,
        ]);

        $this->mock(FirestoreOrderService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('syncCompletion')->once()->andReturnFalse();
        });

        $this->postJson("/api/orders/{$order->id}/complete", [
            'employee_uid' => 'firebase-employee-7',
        ])->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.status', Order::STATUS_COMPLETED);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_COMPLETED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
        ]);
    }
}
