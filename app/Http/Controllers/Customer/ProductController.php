<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Tampilkan detail satu produk berdasarkan slug.
     */
    public function show(string $slug): View
    {
        $product = Product::query()
            ->active()
            ->with('category')
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Product::query()
            ->active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        return view('customer.product-detail', [
            'product' => $product,
            'related' => $related,
        ]);
    }
}
