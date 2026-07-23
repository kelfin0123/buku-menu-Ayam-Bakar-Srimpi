<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        [$products, $error] = $this->loadProducts();
        $filtered = $this->filterProducts($products, $request);

        Log::info('MenuController products sent to view', [
            'count' => $filtered->count(),
            'has_error' => $error !== null,
        ]);

        return view('customer.menu', [
            'categories' => $this->categories($products),
            'products' => $filtered,
            'firestoreError' => $error,
            'activeCategory' => $request->string('category', 'semua')->toString(),
            'searchTerm' => $request->string('search')->toString(),
        ]);
    }

    public function filter(Request $request): JsonResponse
    {
        [$products, $error] = $this->loadProducts();
        $filtered = $this->filterProducts($products, $request);
        $page = max(1, $request->integer('page', 1));
        $perPage = 12;
        $paginator = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
        );

        return response()->json([
            'html' => view('customer.partials.product-grid', [
                'products' => $paginator,
                'firestoreError' => $error,
            ])->render(),
            'error' => $error,
            'has_more' => $paginator->hasMorePages(),
            'next_page' => $paginator->currentPage() + 1,
        ], $error === null ? 200 : 502);
    }

    private function loadProducts(): array
    {
        $products = Product::query()
            ->with('category')
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product) => [
                'id' => (string) ($product->firestore_id ?: $product->id),
                'name' => $product->name,
                'description' => (string) $product->description,
                'category' => $product->category?->name ?? 'Lainnya',
                'price' => (float) $product->final_price,
                'stock' => (int) ($product->stock ?? 0),
                'minimumStock' => 0,
                'barcode' => '',
                'imageUrl' => $product->image_url,
                'isActive' => true,
                'createdAt' => $product->created_at,
                'updatedAt' => $product->updated_at,
            ]);

        return [$products, null];
    }

    private function filterProducts(Collection $products, Request $request): Collection
    {
        $category = $request->string('category')->toString();
        $search = Str::lower($request->string('search')->toString());

        return $products
            ->when($category !== '' && $category !== 'semua', fn (Collection $items) => $items->filter(fn (array $product) => Str::slug($product['category']) === $category)
            )
            ->when($search !== '', fn (Collection $items) => $items->filter(fn (array $product) => str_contains(Str::lower($product['name']), $search))
            )
            ->values();
    }

    private function categories(Collection $products): Collection
    {
        return $products
            ->pluck('category')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $name) => (object) [
                'name' => $name,
                'slug' => Str::slug($name),
                'icon' => 'tag',
            ]);
    }
}
