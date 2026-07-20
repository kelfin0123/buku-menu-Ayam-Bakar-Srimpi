<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrderProductResolver
{
    private ?Collection $firestoreProducts = null;

    public function __construct(private readonly FirestoreProductService $firestore) {}

    public function resolve(string $firestoreId): ?Product
    {
        $documentId = Str::afterLast($firestoreId, '/');
        $product = Product::query()
            ->whereIn('firestore_id', array_unique([$firestoreId, $documentId, "products/{$documentId}"]))
            ->first();

        if ($product) {
            return $product;
        }

        $source = $this->products()->first(fn (array $item) =>
            $item['id'] === $firestoreId || $item['id'] === $documentId
        );

        if (!$source) {
            return null;
        }

        $categoryName = $source['category'] ?: 'Lainnya';
        $category = Category::firstOrCreate(
            ['name' => $categoryName],
            [
                'slug' => Str::slug($categoryName),
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        // The orders schema requires a product FK. This row is an order
        // reference/cache; Firestore remains the menu's source of truth.
        return Product::updateOrCreate(
            ['firestore_id' => $firestoreId],
            [
                'category_id' => $category->id,
                'name' => $source['name'],
                'slug' => Str::slug($source['name']).'-'.substr(sha1($firestoreId), 0, 10),
                'description' => $source['description'],
                'price' => max(0, (int) $source['price']),
                'image' => $source['imageUrl'],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }

    private function products(): Collection
    {
        return $this->firestoreProducts ??= collect($this->firestore->getProducts());
    }
}
