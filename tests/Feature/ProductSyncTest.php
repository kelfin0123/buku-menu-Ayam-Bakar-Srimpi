<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\FirestoreProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProductSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_upserts_by_firestore_id_and_product_is_immediately_visible_on_website(): void
    {
        $payload = [
            'firestore_id' => 'firestore-nasi-goreng',
            'name' => 'Nasi Goreng',
            'description' => '',
            'category' => 'Makanan',
            'price' => 25000,
            'cost_price' => 12000,
            'stock' => 25,
            'minimum_stock' => 5,
            'barcode' => '',
            'is_active' => true,
        ];

        $this->postJson('/api/products/sync', $payload)
            ->assertOk()
            ->assertJsonPath('data.firestore_id', 'firestore-nasi-goreng')
            ->assertJsonPath('data.costPrice', 12000)
            ->assertJsonPath('data.minimumStock', 5)
            ->assertJsonPath('data.isActive', true);

        $this->postJson('/api/products/sync', [
            ...$payload,
            'name' => 'Nasi Goreng Spesial',
            'price' => 27000,
            'stock' => 20,
        ])->assertOk();

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', [
            'firestore_id' => 'firestore-nasi-goreng',
            'name' => 'Nasi Goreng Spesial',
            'price' => 27000,
            'stock' => 20,
        ]);

        $this->get('/menu')
            ->assertOk()
            ->assertSee('Nasi Goreng Spesial')
            ->assertSee('images/no-image.png', false);
    }

    public function test_standard_create_endpoint_accepts_camel_case_and_upserts_by_firestore_id(): void
    {
        $payload = [
            'firestore_id' => 'firestore-standard-endpoint',
            'name' => 'Sate Ayam',
            'description' => '',
            'category' => 'Makanan',
            'price' => 24000,
            'costPrice' => 10000,
            'stock' => 12,
            'minimumStock' => 4,
            'barcode' => '',
            'isActive' => true,
            'imageUrl' => null,
        ];

        $this->postJson('/api/products', $payload)
            ->assertOk()
            ->assertJsonPath('data.cost_price', 10000)
            ->assertJsonPath('data.minimum_stock', 4)
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/products', [
            ...$payload,
            'name' => 'Sate Ayam Baru',
            'stock' => 9,
        ])->assertOk();

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', [
            'firestore_id' => 'firestore-standard-endpoint',
            'name' => 'Sate Ayam Baru',
            'cost_price' => 10000,
            'minimum_stock' => 4,
            'stock' => 9,
            'is_active' => true,
        ]);
    }

    public function test_sync_with_image_uses_one_url_and_inactive_or_deleted_product_is_hidden(): void
    {
        Storage::fake('public');

        $create = $this->post('/api/products/sync', [
            'firestore_id' => 'firestore-image-sync',
            'name' => 'Ayam Bakar',
            'category' => 'Makanan',
            'price' => 30000,
            'stock' => 10,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('ayam.png'),
        ])->assertOk();

        $imageUrl = $create->json('data.image_url');
        $product = Product::where('firestore_id', 'firestore-image-sync')->firstOrFail();

        $this->assertSame($imageUrl, $product->image_url);
        Storage::disk('public')->assertExists($product->image);
        $this->get('/menu')->assertSee($imageUrl, false);

        $this->putJson('/api/products/firestore-image-sync', [
            'name' => 'Ayam Bakar Baru',
            'price' => 32000,
            'stock' => 7,
            'is_active' => false,
        ])->assertOk();

        $this->get('/menu')
            ->assertDontSee('Ayam Bakar Baru');

        $imagePath = $product->fresh()->image;
        $this->deleteJson('/api/products/firestore-image-sync')->assertOk();
        $this->assertDatabaseMissing('products', ['firestore_id' => 'firestore-image-sync']);
        Storage::disk('public')->assertMissing($imagePath);
    }

    public function test_firestore_command_supports_dry_run_and_is_idempotent_without_deleting_old_products(): void
    {
        $category = Category::create([
            'name' => 'Lama',
            'slug' => 'lama',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Lama',
            'slug' => 'produk-lama',
            'price' => 10000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $firestore = Mockery::mock(FirestoreProductService::class);
        $firestore->shouldReceive('getRawDocuments')
            ->times(3)
            ->andReturn([$this->firestoreDocument()]);
        $this->app->instance(FirestoreProductService::class, $firestore);

        $this->artisan('products:sync-from-firestore', ['--dry-run' => true])
            ->expectsOutputToContain('Created: 1')
            ->assertSuccessful();
        $this->assertDatabaseCount('products', 1);

        $this->artisan('products:sync-from-firestore')
            ->expectsOutputToContain('Created: 1')
            ->assertSuccessful();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseHas('products', ['name' => 'Produk Lama']);

        $this->artisan('products:sync-from-firestore')
            ->expectsOutputToContain('Skipped: 1')
            ->assertSuccessful();
        $this->assertDatabaseCount('products', 2);
    }

    private function firestoreDocument(): array
    {
        return [
            'name' => 'projects/demo/databases/(default)/documents/products/firestore-command',
            'fields' => [
                'name' => ['stringValue' => 'Produk Firestore'],
                'description' => ['stringValue' => 'Produk lama'],
                'category' => ['stringValue' => 'Makanan'],
                'price' => ['integerValue' => '22000'],
                'costPrice' => ['integerValue' => '11000'],
                'stock' => ['integerValue' => '15'],
                'minimumStock' => ['integerValue' => '5'],
                'barcode' => ['stringValue' => 'ABC'],
                'isActive' => ['booleanValue' => true],
            ],
        ];
    }
}
