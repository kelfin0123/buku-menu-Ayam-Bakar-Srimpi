<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroBanner;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PublicWebsiteContentController extends Controller
{
    public function __construct(private readonly PromotionService $promotions) {}

    public function heroBanners(): JsonResponse
    {
        $items = Cache::remember('public.hero_banners', 60, fn () => HeroBanner::query()
            ->currentlyActive()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (HeroBanner $banner) => $this->heroData($banner)));

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function promotions(): JsonResponse
    {
        $items = Cache::remember('public.promotions', 60, fn () => Promotion::query()
            ->with('product')
            ->currentlyActive()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Promotion $promotion) => $this->promotionData($promotion)));

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function heroData(HeroBanner $banner): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'highlight_text' => $banner->highlight_text,
            'subtitle' => $banner->subtitle,
            'description' => $banner->description,
            'button_text' => $banner->button_text,
            'button_url' => $banner->button_url,
            'image_url' => $banner->resolved_image_url,
            'is_active' => $banner->is_active,
            'sort_order' => $banner->sort_order,
            'starts_at' => $banner->starts_at?->toISOString(),
            'ends_at' => $banner->ends_at?->toISOString(),
        ];
    }

    public function promotionData(Promotion $promotion): array
    {
        $product = $promotion->product;
        $promoPrice = $product
            ? $this->promotions->calculatePromoPrice($promotion, $product)
            : ($promotion->promo_price !== null ? (int) $promotion->promo_price : null);

        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'title' => $promotion->title,
            'description' => $promotion->description,
            'image_url' => $promotion->image_url,
            'discount_type' => $promotion->discount_type,
            'discount_value' => $promotion->discount_value !== null
                ? (float) $promotion->discount_value : null,
            'promo_price' => $promoPrice,
            'badge_text' => $promotion->badge_text,
            'is_active' => $promotion->is_active,
            'sort_order' => $promotion->sort_order,
            'starts_at' => $promotion->starts_at?->toISOString(),
            'ends_at' => $promotion->ends_at?->toISOString(),
            'product' => $product ? [
                'id' => $product->id,
                'firestore_id' => $product->firestore_id,
                'name' => $product->name,
                'price' => (int) $product->price,
                'stock' => (int) $product->stock,
                'image_url' => $product->image_url,
            ] : null,
        ];
    }
}
