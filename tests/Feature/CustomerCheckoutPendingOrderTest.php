<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
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
            'customer_phone' => '0812-3456-7890',
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
            'customer_phone' => '6281234567890',
            'table_number' => 'A1',
            'subtotal' => 30000,
            'shipping_cost' => 0,
            'total' => 30000,
            'status' => 'waiting_payment',
            'payment_status' => 'pending',
        ]);

        $order = Order::where('customer_name', 'Budi')->firstOrFail();
        $this->assertNotNull($order->expires_at);
        $this->getJson("/api/orders/code/{$order->order_code}")
            ->assertOk()
            ->assertJsonPath('data.customer_phone', '6281234567890')
            ->assertJsonPath('data.expires_at', $order->expires_at->toISOString());
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

    public function test_customer_can_choose_cash_and_order_becomes_incoming(): void
    {
        $order = Order::create([
            'order_code' => 'ABS-20260720-PAY01',
            'customer_name' => 'Dina',
            'customer_phone' => '',
            'table_number' => 'C3',
            'subtotal' => 10000,
            'shipping_cost' => 0,
            'total' => 10000,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_WAITING_PAYMENT,
        ]);

        $this->get(route('order.show', $order->order_code))
            ->assertOk()
            ->assertDontSee('Ongkir')
            ->assertDontSee('Kirim Nota ke WhatsApp')
            ->assertDontSee('Nomor WhatsApp pelanggan belum tersedia')
            ->assertSee('Rp 10.000')
            ->assertSee('Bayar Tunai')
            ->assertSee('Bayar QRIS');

        $this->post(route('checkout.payment.select', $order->order_code), [
            'payment_method' => 'cash',
        ])->assertRedirect(route('checkout.payment', $order->order_code));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'cash',
            'status' => Order::STATUS_NEW_ORDER,
        ]);
        $this->getJson('/api/orders/incoming')->assertOk()
            ->assertJsonFragment(['order_code' => $order->order_code]);
    }

    public function test_customer_website_never_shows_send_receipt_to_whatsapp(): void
    {
        $order = Order::create([
            'order_code' => 'ABS-20260720-NOWA',
            'customer_name' => 'Rani',
            'customer_phone' => '628123456789',
            'table_number' => 'D4',
            'subtotal' => 20000,
            'shipping_cost' => 0,
            'total' => 20000,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_WAITING_PAYMENT,
        ]);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertDontSee('Kirim Nota ke WhatsApp');
        $this->get(route('order.show', $order->order_code))
            ->assertOk()
            ->assertDontSee('Kirim Nota ke WhatsApp')
            ->assertSee('Pilih Metode Pembayaran');

        $receiptUrl = URL::temporarySignedRoute(
            'receipt.show',
            now()->addMinute(),
            ['orderCode' => $order->order_code],
        );
        $this->get($receiptUrl)
            ->assertOk()
            ->assertDontSee('Kirim Nota ke WhatsApp');
    }

    public function test_customer_can_choose_qris_without_whatsapp_receipt_button(): void
    {
        $order = Order::create([
            'order_code' => 'ABS-20260720-QRIS',
            'customer_name' => 'Rani',
            'customer_phone' => '628123456789',
            'table_number' => 'D4',
            'subtotal' => 20000,
            'shipping_cost' => 0,
            'total' => 20000,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_WAITING_PAYMENT,
        ]);

        $this->post(route('checkout.payment.select', $order->order_code), [
            'payment_method' => Order::PAYMENT_METHOD_QRIS,
        ])->assertRedirect(route('checkout.payment', $order->order_code));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_phone' => '628123456789',
            'payment_method' => Order::PAYMENT_METHOD_QRIS,
            'payment_status' => Order::PAYMENT_STATUS_PENDING,
            'status' => Order::STATUS_WAITING_PAYMENT,
        ]);
        $this->get(route('checkout.payment', $order->order_code))
            ->assertOk()
            ->assertDontSee('Kirim Nota ke WhatsApp');
    }
}
