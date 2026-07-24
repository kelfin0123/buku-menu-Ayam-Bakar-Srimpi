<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireFirebaseOwner;
use App\Models\Category;
use App\Models\HeroBanner;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\FirestoreOrderService;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class WebsiteContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_upload_toggle_and_delete_hero(): void
    {
        Storage::fake('public');

        $response = $this->withoutMiddleware(RequireFirebaseOwner::class)
            ->post('/api/owner/hero-banners', [
                'title' => 'Hero Test',
                'highlight_text' => 'Aktif',
                'image' => UploadedFile::fake()->image('hero.jpg', 1200, 500),
                'is_active' => '1',
                'sort_order' => '2',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Hero Test');

        $banner = HeroBanner::firstOrFail();
        $oldPath = $banner->image;
        Storage::disk('public')->assertExists($oldPath);
        $this->withoutMiddleware(RequireFirebaseOwner::class)
            ->getJson("/api/owner/hero-banners/{$banner->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $banner->id);
        $this->getJson('/api/hero-banners')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withoutMiddleware(RequireFirebaseOwner::class)
            ->post("/api/owner/hero-banners/{$banner->id}", [
                'title' => 'Hero Diperbarui',
                'image' => UploadedFile::fake()->image('hero-new.webp', 1200, 500),
                'is_active' => '1',
                'sort_order' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Hero Diperbarui');
        Storage::disk('public')->assertMissing($oldPath);

        $this->withoutMiddleware(RequireFirebaseOwner::class)
            ->patchJson("/api/owner/hero-banners/{$banner->id}/toggle")
            ->assertOk();
        Cache::forget('public.hero_banners');
        $this->getJson('/api/hero-banners')->assertJsonCount(0, 'data');

        $path = $banner->fresh()->image;
        $this->withoutMiddleware(RequireFirebaseOwner::class)
            ->deleteJson("/api/owner/hero-banners/{$banner->id}")
            ->assertOk();
        Storage::disk('public')->assertMissing($path);
    }

    public function test_public_hero_honors_schedule_and_order(): void
    {
        HeroBanner::create([
            'title' => 'Belum Mulai',
            'starts_at' => now()->addDay(),
            'sort_order' => 0,
        ]);
        HeroBanner::create(['title' => 'Kedua', 'sort_order' => 2]);
        HeroBanner::create(['title' => 'Pertama', 'sort_order' => 1]);

        $this->getJson('/api/hero-banners')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Pertama')
            ->assertJsonPath('data.1.title', 'Kedua');
    }

    public function test_promotion_price_is_calculated_by_backend_and_expires(): void
    {
        $category = Category::create([
            'name' => 'Makanan',
            'slug' => 'makanan',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Ayam Promo',
            'slug' => 'ayam-promo',
            'price' => 25000,
            'stock' => 10,
            'is_active' => true,
            'firestore_id' => 'promo-product',
        ]);
        $promotion = Promotion::create([
            'name' => 'Diskon Sepuluh',
            'title' => 'Promo Ayam',
            'product_id' => $product->id,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $service = app(PromotionService::class);
        $this->assertSame(22500, $service->validatePromotionAtCheckout($product));
        $this->getJson('/api/promotions')
            ->assertOk()
            ->assertJsonPath('data.0.promo_price', 22500)
            ->assertJsonPath('data.0.product.name', 'Ayam Promo');
        $this->get('/menu?category=promo')
            ->assertOk()
            ->assertSee('Ayam Promo')
            ->assertSee('Rp 22.500')
            ->assertDontSee('Belum ada produk yang berhasil dibaca dari Firebase.');

        $this->mock(FirestoreOrderService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sync')->once()->andReturnTrue();
        });
        $this->postJson('/api/checkout', [
            'customer_name' => 'Pembeli Promo',
            'table_number' => 'P-1',
            'payment_method' => 'cash',
            'items' => [[
                'product_id' => $product->firestore_id,
                'qty' => 2,
                'price' => 1,
            ]],
        ])->assertCreated()->assertJsonPath('data.total', 45000);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'price' => 22500,
            'qty' => 2,
        ]);

        $promotion->update(['ends_at' => now()->subMinute()]);
        Cache::forget('public.promotions');
        $this->assertSame(25000, $service->validatePromotionAtCheckout($product));
        $this->getJson('/api/promotions')->assertJsonCount(0, 'data');
    }

    public function test_management_endpoints_are_owner_only(): void
    {
        $this->getJson('/api/owner/hero-banners')->assertUnauthorized();
        $this->getJson('/api/owner/promotions')->assertUnauthorized();
    }
}
