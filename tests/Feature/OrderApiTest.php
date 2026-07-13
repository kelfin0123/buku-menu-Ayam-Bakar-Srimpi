<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

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
            'status' => 'menunggu_konfirmasi',
        ]);

        $response = $this->getJson('/api/orders/pending');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $order->id]);

        $acceptResponse = $this->postJson("/api/orders/{$order->id}/accept", [
            'employee_id' => 7,
        ]);

        $acceptResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'diproses');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'diproses',
            'employee_id' => 7,
        ]);

        $finishResponse = $this->postJson("/api/orders/{$order->id}/finish");
        $finishResponse->assertOk()
            ->assertJsonPath('data.status', 'selesai');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'selesai',
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
            'status' => 'menunggu_konfirmasi',
        ]);

        $rejectResponse = $this->postJson("/api/orders/{$rejectedOrder->id}/reject", [
            'employee_id' => 8,
            'rejection_reason' => 'Stok habis',
        ]);

        $rejectResponse->assertOk()
            ->assertJsonPath('data.status', 'ditolak');

        $this->assertDatabaseHas('orders', [
            'id' => $rejectedOrder->id,
            'status' => 'ditolak',
            'rejection_reason' => 'Stok habis',
        ]);
    }
}
