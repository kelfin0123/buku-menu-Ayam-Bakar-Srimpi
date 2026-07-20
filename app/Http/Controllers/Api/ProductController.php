<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductStoreRequest;
use App\Http\Requests\Api\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductStorageService;
use App\Services\OrderProductResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected ProductStorageService $storageService;

    public function __construct(
        ProductStorageService $storageService,
        private readonly OrderProductResolver $orderProductResolver,
    )
    {
        $this->storageService = $storageService;
    }

    public function index(): JsonResponse
    {
        $products = Product::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
        ]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        try {
            $stored = $this->storageService->store($request->file('image'));

            return response()->json([
                'success' => true,
                'image_url' => $stored['url'],
                'image_path' => $stored['path'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunggah gambar: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(ProductStoreRequest $request): JsonResponse
    {
        $data = $request->only(['name', 'price', 'description', 'firestore_id', 'is_active', 'is_promo', 'promo_price', 'sort_order']);

        $slugBase = Str::slug($data['name']);
        $data['slug'] = $slugBase . '-' . time();

        $categoryId = $request->input('category_id');
        if (! $categoryId && $request->filled('category')) {
            $category = Category::firstOrCreate(
                ['name' => $request->input('category')],
                [
                    'slug' => Str::slug($request->input('category')),
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );
            $categoryId = $category->id;
        }
        $data['category_id'] = $categoryId;

        if ($request->hasFile('image')) {
            $stored = $this->storageService->store($request->file('image'));
            $data['image'] = $stored['url'];
        } elseif ($request->filled('image')) {
            $data['image'] = $request->input('image');
        }

        $product = Product::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil disimpan',
            'data' => new ProductResource($product),
        ]);
    }

    public function update(ProductUpdateRequest $request, $productIdentifier): JsonResponse
    {
        $product = Product::query()
            ->where('id', $productIdentifier)
            ->orWhere('firestore_id', $productIdentifier)
            ->firstOrFail();

        $data = $request->only(['name', 'price', 'description', 'firestore_id', 'is_active', 'is_promo', 'promo_price', 'sort_order']);

        if (isset($data['name'])) {
            $slugBase = Str::slug($data['name']);
            $data['slug'] = $slugBase . '-' . time();
        }

        $categoryId = $request->input('category_id');
        if (! is_null($categoryId) && $categoryId !== '') {
            $data['category_id'] = $categoryId;
        } elseif ($request->filled('category')) {
            $category = Category::firstOrCreate(
                ['name' => $request->input('category')],
                [
                    'slug' => Str::slug($request->input('category')),
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );
            $data['category_id'] = $category->id;
        }

        if ($request->hasFile('image')) {
            $stored = $this->storageService->store($request->file('image'));
            $this->storageService->delete($product->image);
            $data['image'] = $stored['url'];
        } elseif ($request->filled('image')) {
            $data['image'] = $request->input('image');
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui',
            'data' => new ProductResource($product->fresh()),
        ]);
    }

    public function destroy(Request $request, $productIdentifier): JsonResponse
    {
        $product = Product::query()
            ->where('id', $productIdentifier)
            ->orWhere('firestore_id', $productIdentifier)
            ->firstOrFail();

        $this->storageService->delete($product->image);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus',
        ]);
    }

    public function validateProducts(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'string',
        ]);

        $requestedIds = $request->input('product_ids');
        $existingIds = collect($requestedIds)
            ->filter(fn (string $id) => $this->orderProductResolver->resolve($id) !== null)
            ->values()
            ->all();

        return response()->json([
            'valid' => count($requestedIds) === count($existingIds),
            'valid_ids' => $existingIds,
            'invalid_ids' => array_diff($requestedIds, $existingIds),
        ]);
    }
}
