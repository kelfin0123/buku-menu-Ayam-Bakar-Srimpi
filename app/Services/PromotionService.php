<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;

class PromotionService
{
    public function isActive(Promotion $promotion): bool
    {
        $now = now();

        return $promotion->is_active
            && (! $promotion->starts_at || $promotion->starts_at->lte($now))
            && (! $promotion->ends_at || $promotion->ends_at->gte($now));
    }

    public function calculatePromoPrice(Promotion $promotion, Product $product): int
    {
        $normal = max(0, (int) $product->price);
        $value = max(0, (float) ($promotion->discount_value ?? 0));

        return match ($promotion->discount_type) {
            'percentage' => max(0, (int) round($normal * (1 - min(100, $value) / 100))),
            'fixed' => max(0, (int) round($normal - $value)),
            'special_price' => max(0, (int) round($promotion->promo_price ?? $value)),
            default => $normal,
        };
    }

    public function getActivePromotionForProduct(Product $product): ?Promotion
    {
        return Promotion::query()
            ->currentlyActive()
            ->where('product_id', $product->id)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->first();
    }

    public function validatePromotionAtCheckout(Product $product): int
    {
        $promotion = $this->getActivePromotionForProduct($product);

        return $promotion
            ? $this->calculatePromoPrice($promotion, $product)
            : (int) $product->final_price;
    }
}
