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
        // Sync from Firebase is now handled by background scheduler
        // No need to sync on every page load

        $categories = Category::query()
            ->active()
            ->ordered()
            ->get();

        $products = $this->filteredProducts($request)->get();

        // Log product data for debugging
        \Log::info('MenuController - Products sent to view', $products->map(function ($p) {
            return [
                'id' => $p->id,
                'firestore_id' => $p->firestore_id,
                'name' => $p->name,
            ];
        })->toArray());

        return view('customer.menu', [
            'categories'      => $categories,
            'products'        => $products,
            'activeCategory'  => $request->get('category', 'semua'),
            'searchTerm'      => $request->get('search', ''),
        ]);
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
