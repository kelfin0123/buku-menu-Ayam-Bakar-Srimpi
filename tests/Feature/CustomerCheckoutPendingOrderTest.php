<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

        $response->assertRedirect(route('order.show', Order::where('customer_name', 'Budi')->firstOrFail()->order_code));
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

    public function test_checkout_resolves_a_product_directly_from_firestore_instead_of_returning_404(): void
    {
        config()->set('firebase.project_id', 'test-project');
        config()->set('firebase.products_collection', 'products');
        config()->set('firebase.credentials', null);
        config()->set('firebase.credentials_base64', null);
        config()->set('firebase.client_email', null);
        config()->set('firebase.private_key', null);

        Http::fake([
            'https://firestore.googleapis.com/v1/projects/test-project/databases/(default)/documents/products*' => Http::response([
                'documents' => [[
                    'name' => 'projects/test-project/databases/(default)/documents/products/firebase-1',
                    'fields' => [
                        'name' => ['stringValue' => 'Ayam Firebase'],
                        'category' => ['stringValue' => 'Makanan'],
                        'price' => ['integerValue' => '25000'],
                        'stock' => ['integerValue' => '10'],
                    ],
                ]],
            ]),
        ]);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Siti',
            'table_number' => 'B2',
            'items' => [['firestore_id' => 'firebase-1', 'qty' => 1]],
        ]);

        $order = Order::where('customer_name', 'Siti')->firstOrFail();
        $response->assertRedirect(route('order.show', $order->order_code));
        $this->assertDatabaseHas('products', ['firestore_id' => 'firebase-1']);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'qty' => 1]);
    }

}
