<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuControllerSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_reads_products_and_laravel_image_url_from_database(): void
    {
        $category = Category::create([
            'name' => 'Makanan',
            'slug' => 'makanan',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Nasi Goreng',
            'slug' => 'nasi-goreng',
            'description' => 'Enak dan hangat',
            'price' => 20000,
            'image' => 'products/nasi.jpg',
            'image_url' => 'https://menu.example/storage/products/nasi.jpg',
            'firestore_id' => 'doc-1',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Nasi Goreng')
            ->assertSee('Makanan')
            ->assertSee('doc-1')
            ->assertSee('https://menu.example/storage/products/nasi.jpg');
    }

    public function test_menu_does_not_show_inactive_database_products(): void
    {
        $category = Category::create([
            'name' => 'Minuman',
            'slug' => 'minuman',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Nonaktif',
            'slug' => 'produk-nonaktif',
            'price' => 10000,
            'firestore_id' => 'inactive-doc',
            'is_active' => false,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Produk Nonaktif');
    }
}
