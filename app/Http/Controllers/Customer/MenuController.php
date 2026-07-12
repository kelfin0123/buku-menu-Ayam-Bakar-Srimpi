<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class MenuController extends Controller
{
    /**
     * Tampilkan halaman utama Digital Menu (Beranda + Menu).
     */
    public function index(Request $request): View
    {
        $this->syncFromFirestore();

        $categories = Category::query()
            ->active()
            ->ordered()
            ->get();

        $products = $this->filteredProducts($request)->get();

        return view('customer.menu', [
            'categories'      => $categories,
            'products'        => $products,
            'activeCategory'  => $request->get('category', 'semua'),
            'searchTerm'      => $request->get('search', ''),
        ]);
    }

    /**
     * Sync products and categories from Firestore REST API.
     */
    private function syncFromFirestore(): void
    {
        \Illuminate\Support\Facades\Cache::remember('firestore_products_sync', 300, function () {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(5)
                    ->get('https://firestore.googleapis.com/v1/projects/kasir-40363/databases/(default)/documents/products');

                if ($response->successful()) {
                    $data = $response->json();
                    $documents = $data['documents'] ?? [];
                    $firestoreIds = [];

                    foreach ($documents as $doc) {
                        $namePath = $doc['name'] ?? '';
                        $parts = explode('/', $namePath);
                        $firestoreId = end($parts);
                        if (!$firestoreId) {
                            continue;
                        }

                        $firestoreIds[] = $firestoreId;
                        $fields = $doc['fields'] ?? [];

                        $name = $fields['name']['stringValue'] ?? '';
                        $price = $fields['price']['doubleValue'] ?? $fields['price']['integerValue'] ?? 0;
                        $categoryName = $fields['category']['stringValue'] ?? 'Umum';
                        $description = $fields['description']['stringValue'] ?? '';
                        $imageUrl = $fields['imageUrl']['stringValue'] ?? null;
                        $isActive = $fields['isActive']['booleanValue'] ?? true;

                        // Get or create Category
                        $category = Category::firstOrCreate(
                            ['name' => $categoryName],
                            [
                                'slug' => \Illuminate\Support\Str::slug($categoryName),
                                'is_active' => true,
                                'sort_order' => 1
                            ]
                        );

                        // Update or create Product
                        Product::updateOrCreate(
                            ['firestore_id' => $firestoreId],
                            [
                                'category_id' => $category->id,
                                'name' => $name,
                                'slug' => \Illuminate\Support\Str::slug($name) . '-' . $firestoreId,
                                'description' => $description,
                                'price' => intval($price),
                                'image' => $imageUrl,
                                'is_active' => $isActive,
                                'sort_order' => 1
                            ]
                        );
                    }

                    // Delete all local products that do not have a firestore_id
                    Product::whereNull('firestore_id')->delete();

                    // Delete local products that were deleted in Firestore
                    if (!empty($firestoreIds)) {
                        Product::whereNotNull('firestore_id')
                            ->whereNotIn('firestore_id', $firestoreIds)
                            ->delete();
                    }

                    // Delete empty categories that do not have any products
                    Category::doesntHave('products')->delete();
                }
            } catch (\Throwable $e) {
                // Fail silently
                logger('Firestore sync failed: ' . $e->getMessage());
            }

            return true;
        });
    }

    /**
     * Endpoint AJAX untuk filter kategori & search (dipakai oleh menu.js)
     * tanpa reload halaman.
     */
    public function filter(Request $request): JsonResponse
    {
        $products = $this->filteredProducts($request)
            ->paginate(12);

        return response()->json([
            'html'         => view('customer.partials.product-grid', [
                'products' => $products,
            ])->render(),
            'has_more'     => $products->hasMorePages(),
            'next_page'    => $products->currentPage() + 1,
        ]);
    }

    /**
     * Query builder produk dengan filter kategori (slug) & pencarian nama.
     */
    private function filteredProducts(Request $request)
    {
        $query = Product::query()->with('category')->active();

        $category = $request->get('category');

        if ($category && $category !== 'semua') {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->orderBy('sort_order');
    }
}
