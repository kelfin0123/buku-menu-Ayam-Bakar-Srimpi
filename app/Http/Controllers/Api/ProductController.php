<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProductStoreRequest;
use App\Http\Requests\Api\ProductUpdateRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected ProductStorageService $storageService;

    public function __construct(ProductStorageService $storageService)
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
        $this->storageService = $storageService;
    }

    public function store(ProductStoreRequest $request): JsonResponse
    {
        $data = $request->only(['name', 'price', 'category_id', 'description']);

        // generate unique slug
        $slugBase = Str::slug($data['name']);
        $data['slug'] = $slugBase . '-' . time();

        if ($request->hasFile('image')) {
            $data['image'] = $this->storageService->store($request->file('image'));
        }

        $product = Product::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil disimpan',
            'data' => new ProductResource($product),
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product): JsonResponse
    {
        $data = $request->only(['name', 'price', 'category_id', 'description']);

        if (isset($data['name'])) {
            $slugBase = Str::slug($data['name']);
            $data['slug'] = $slugBase . '-' . time();
        }

        if ($request->hasFile('image')) {
            // store new image
            $newPath = $this->storageService->store($request->file('image'));
            // delete old image
            $this->storageService->delete($product->image);
            $data['image'] = $newPath;
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui',
            'data' => new ProductResource($product->fresh()),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        // delete image from storage
        $this->storageService->delete($product->image);

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus',
        ]);
    }
}
