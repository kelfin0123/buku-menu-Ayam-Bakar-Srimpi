<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailCartIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_button_uses_firestore_id_for_cart(): void
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

        $response = $this->get(route('product.show', $product->slug));

        $response->assertOk();
        $response->assertSee('data-id="' . $product->firestore_id . '"', false);
        $response->assertDontSee('data-id="' . $product->id . '"', false);
    }
}
