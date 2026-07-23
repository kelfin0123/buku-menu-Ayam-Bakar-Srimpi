<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductStoreRequest;
use App\Http\Requests\Api\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\OrderProductResolver;
use App\Services\ProductStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected ProductStorageService $storageService;

    public function __construct(
        ProductStorageService $storageService,
        private readonly OrderProductResolver $orderProductResolver,
    ) {
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
                'path' => $stored['path'],
                'image_path' => $stored['path'],
                'message' => 'Upload berhasil',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunggah gambar: '.$e->getMessage(),
            ], 500);
        }
    }

    public function store(ProductStoreRequest $request): JsonResponse
    {
        if ($request->filled('firestore_id')) {
            return $this->sync($request);
        }

        $data = $request->only([
            'name', 'price', 'description', 'firestore_id', 'is_active',
            'is_promo', 'promo_price', 'sort_order', 'cost_price', 'stock',
            'minimum_stock', 'barcode',
        ]);
        $data['barcode'] = (string) ($data['barcode'] ?? '');

        $slugBase = Str::slug($data['name']);
        $data['slug'] = $slugBase.'-'.time();

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

        $stored = null;
        try {
            if ($request->hasFile('image')) {
                $stored = $this->storageService->store($request->file('image'));
                $data['image'] = $stored['path'];
                $data['image_url'] = $stored['url'];
            } elseif ($request->filled('image')) {
                $data['image'] = $request->input('image');
                $data['image_url'] = $request->input('image');
            } elseif ($request->filled('image_url')) {
                $data['image_url'] = $request->input('image_url');
            }

            $product = DB::transaction(fn () => Product::create($data));
        } catch (\Throwable $e) {
            $this->storageService->delete($stored['path'] ?? null);
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil disimpan',
            'data' => new ProductResource($product),
        ]);
    }

    public function sync(ProductStoreRequest $request): JsonResponse
    {
        $request->validate([
            'firestore_id' => ['required', 'string', 'max:255'],
        ]);
        $data = $request->only([
            'name', 'price', 'description', 'firestore_id', 'is_active',
            'cost_price', 'stock', 'minimum_stock', 'barcode',
        ]);
        $data['cost_price'] = (int) ($data['cost_price'] ?? 0);
        $data['stock'] = (int) ($data['stock'] ?? 0);
        $data['minimum_stock'] = (int) ($data['minimum_stock'] ?? 5);
        $data['barcode'] = (string) ($data['barcode'] ?? '');
        $firestoreId = (string) $request->input('firestore_id');
        $data['slug'] = Str::slug($data['name']).'-'.substr(sha1($firestoreId), 0, 12);

        $category = Category::firstOrCreate(
            ['name' => $request->input('category', 'Umum')],
            [
                'slug' => Str::slug($request->input('category', 'Umum')),
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
        $data['category_id'] = $category->id;

        $existing = Product::where('firestore_id', $firestoreId)->first();
        $oldImage = $existing?->image;
        $stored = null;

        try {
            if ($request->hasFile('image')) {
                $stored = $this->storageService->store($request->file('image'));
                $data['image'] = $stored['path'];
                $data['image_url'] = $stored['url'];
            } elseif ($request->filled('image_url')) {
                $data['image_url'] = $request->input('image_url');
            }

            $product = DB::transaction(fn () => Product::updateOrCreate(
                ['firestore_id' => $firestoreId],
                $data,
            ));
        } catch (\Throwable $e) {
            $this->storageService->delete($stored['path'] ?? null);
            throw $e;
        }

        if ($stored !== null && $oldImage !== null && $oldImage !== $product->image) {
            $this->storageService->delete($oldImage);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil disinkronkan',
            'data' => new ProductResource($product->fresh()),
        ]);
    }

    public function update(ProductUpdateRequest $request, $productIdentifier): JsonResponse
    {
        $product = Product::query()
            ->where('id', $productIdentifier)
            ->orWhere('firestore_id', $productIdentifier)
            ->first();
        $isNewProduct = $product === null;
        $product ??= new Product(['firestore_id' => (string) $productIdentifier]);

        $data = $request->only([
            'name', 'price', 'description', 'firestore_id', 'is_active',
            'is_promo', 'promo_price', 'sort_order', 'cost_price', 'stock',
            'minimum_stock', 'barcode',
        ]);
        if (array_key_exists('barcode', $data)) {
            $data['barcode'] = (string) ($data['barcode'] ?? '');
        }

        if (isset($data['name'])) {
            $slugBase = Str::slug($data['name']);
            $data['slug'] = $slugBase.'-'.time();
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

        $stored = null;
        $oldImage = $product->image;
        try {
            if ($request->hasFile('image')) {
                $stored = $this->storageService->store($request->file('image'));
                $data['image'] = $stored['path'];
                $data['image_url'] = $stored['url'];
            } elseif ($request->boolean('remove_image')) {
                $data['image'] = null;
                $data['image_url'] = null;
            } elseif ($request->filled('image')) {
                $data['image'] = $request->input('image');
                $data['image_url'] = $request->input('image');
            } elseif ($request->filled('image_url')) {
                $data['image_url'] = $request->input('image_url');
            }

            DB::transaction(function () use ($product, $data, $productIdentifier, $isNewProduct): void {
                if ($isNewProduct) {
                    $product->fill([
                        ...$data,
                        'firestore_id' => (string) $productIdentifier,
                    ])->save();

                    return;
                }

                $product->update($data);
            });
        } catch (\Throwable $e) {
            $this->storageService->delete($stored['path'] ?? null);
            throw $e;
        }

        if ($stored !== null || $request->boolean('remove_image')) {
            $this->storageService->delete($oldImage);
        }

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
            ->first();

        if (! $product) {
            $this->storageService->delete($request->string('image_url')->toString());

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus',
            ]);
        }

        $image = $product->image;
        DB::transaction(fn () => $product->delete());
        $this->storageService->delete($image);

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
