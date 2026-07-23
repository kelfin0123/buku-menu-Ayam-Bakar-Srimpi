<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_image_endpoint_returns_public_url_and_path(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('product.jpg', 640, 480);

        $response = $this->postJson('/api/products/upload', [
            'image' => $file,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'image_url',
                'path',
                'image_path',
                'message',
            ])
            ->assertJsonPath('success', true);

        $this->assertStringContainsString('/storage/products/', $response->json('image_url'));
        $this->assertStringContainsString('products/', $response->json('image_path'));
    }

    public function test_product_create_update_and_delete_manage_image_lifecycle(): void
    {
        Storage::fake('public');
        $category = Category::create([
            'name' => 'Makanan',
            'slug' => 'makanan-image-test',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $create = $this->post('/api/products', [
            'name' => 'Ayam Gambar',
            'price' => 25000,
            'category_id' => $category->id,
            'firestore_id' => 'firestore-image-product',
            'image' => UploadedFile::fake()->image('first.png'),
        ])->assertOk();

        $product = Product::where('firestore_id', 'firestore-image-product')
            ->firstOrFail();
        $firstPath = $product->image;
        Storage::disk('public')->assertExists($firstPath);
        $this->assertStringContainsString(
            '/storage/products/',
            $product->getRawOriginal('image_url'),
        );

        $update = $this->post('/api/products/firestore-image-product', [
            'name' => 'Ayam Gambar',
            'price' => 25000,
            'category_id' => $category->id,
            'firestore_id' => 'firestore-image-product',
            'image' => UploadedFile::fake()->image('second.webp'),
        ])->assertOk();

        $product->refresh();
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($product->image);
        $update->assertJsonPath('data.image_url', $product->image_url);

        $lastPath = $product->image;
        $this->delete('/api/products/firestore-image-product')->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing($lastPath);
    }

    public function test_edit_migrates_a_legacy_firestore_only_product_to_laravel(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/products/legacy-firestore-id', [
            'name' => 'Produk Legacy',
            'price' => 18000,
            'category' => 'Makanan',
            'firestore_id' => 'legacy-firestore-id',
            'image' => UploadedFile::fake()->image('legacy.jpg'),
        ])->assertOk();

        $this->assertDatabaseHas('products', [
            'firestore_id' => 'legacy-firestore-id',
            'name' => 'Produk Legacy',
            'price' => 18000,
        ]);
        $this->assertStringContainsString(
            '/storage/products/',
            $response->json('data.image_url'),
        );
    }
}
