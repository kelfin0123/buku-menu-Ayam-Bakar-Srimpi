<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCheckoutPendingOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_pending_order_from_firestore_id(): void
    {
        $category = Category::create([
            'name' => 'Makanan',
            'slug' => 'makanan',
            'icon' => null,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Nasi Ayam',
            'slug' => 'nasi-ayam',
            'description' => 'Nasi ayam lezat',
            'price' => 15000,
            'promo_price' => null,
            'image' => null,
            'is_promo' => false,
            'is_active' => true,
            'sort_order' => 1,
            'firestore_id' => 'products/abc123',
        ]);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Budi',
            'table_number' => 'A1',
            'items' => [
                [
                    'firestore_id' => $product->firestore_id,
                    'qty' => 2,
                ],
            ],
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Budi',
            'table_number' => 'A1',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $order = Order::where('customer_name', 'Budi')->firstOrFail();
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 2,
        ]);
    }

}
