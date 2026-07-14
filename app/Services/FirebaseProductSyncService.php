<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirebaseProductSyncService
{
    private string $firebaseUrl;
    private SyncResult $result;

    public function __construct()
    {
        $this->firebaseUrl = config('services.firebase.products_url', 'https://firestore.googleapis.com/v1/projects/kasir-40363/databases/(default)/documents/products');
        $this->result = new SyncResult();
    }

    /**
     * Sync products from Firebase to PostgreSQL
     */
    public function sync(): SyncResult
    {
        Log::info('Starting Firebase product sync');

        try {
            $response = Http::timeout(10)
                ->get($this->firebaseUrl);

            if (!$response->successful()) {
                Log::error('Firebase sync failed: HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $this->result->success = false;
                $this->result->error = 'HTTP error: ' . $response->status();
                return $this->result;
            }

            $data = $response->json();
            $documents = $data['documents'] ?? [];
            $firestoreIds = [];

            Log::info('Fetched ' . count($documents) . ' products from Firebase');

            foreach ($documents as $doc) {
                $firestoreId = $this->extractFirestoreId($doc['name'] ?? '');
                if (!$firestoreId) {
                    Log::warning('Skipping document without valid ID', ['doc' => $doc]);
                    continue;
                }

                $firestoreIds[] = $firestoreId;
                $fields = $doc['fields'] ?? [];

                $this->syncProduct($firestoreId, $fields);
            }

            // Delete products that are in PostgreSQL but not in Firebase
            $this->deleteObsoleteProducts($firestoreIds);

            // Delete empty categories
            $this->deleteEmptyCategories();

            $this->result->success = true;
            Log::info('Firebase sync completed', [
                'created' => $this->result->created,
                'updated' => $this->result->updated,
                'deleted' => $this->result->deleted,
            ]);

        } catch (\Throwable $e) {
            Log::error('Firebase sync failed: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->result->success = false;
            $this->result->error = $e->getMessage();
        }

        return $this->result;
    }

    /**
     * Extract Firestore ID from document name
     */
    private function extractFirestoreId(string $name): ?string
    {
        $parts = explode('/', $name);
        $id = end($parts);
        return $id ?: null;
    }

    /**
     * Sync a single product from Firebase to PostgreSQL
     */
    private function syncProduct(string $firestoreId, array $fields): void
    {
        $name = $fields['name']['stringValue'] ?? '';
        $price = $fields['price']['doubleValue'] ?? $fields['price']['integerValue'] ?? 0;
        $categoryName = $fields['category']['stringValue'] ?? 'Umum';
        $description = $fields['description']['stringValue'] ?? '';
        $imageUrl = $fields['imageUrl']['stringValue'] ?? null;
        $isActive = $fields['isActive']['booleanValue'] ?? true;
        $stock = $fields['stock']['integerValue'] ?? 0;
        $minimumStock = $fields['minimumStock']['integerValue'] ?? 5;
        $barcode = $fields['barcode']['stringValue'] ?? '';
        $costPrice = $fields['costPrice']['doubleValue'] ?? $fields['costPrice']['integerValue'] ?? 0;
        $supplier = $fields['supplier']['stringValue'] ?? null;
        $purchaseInvoice = $fields['purchaseInvoice']['stringValue'] ?? null;

        // Get or create Category
        $category = Category::firstOrCreate(
            ['name' => $categoryName],
            [
                'slug' => Str::slug($categoryName),
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // Check if product exists
        $existingProduct = Product::where('firestore_id', $firestoreId)->first();

        if ($existingProduct) {
            // Update existing product
            $changes = $this->detectChanges($existingProduct, $fields, $category->id);

            if (!empty($changes)) {
                $existingProduct->update([
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => Str::slug($name) . '-' . $firestoreId,
                    'description' => $description,
                    'price' => intval($price),
                    'image' => $imageUrl,
                    'is_active' => $isActive,
                    'sort_order' => 1,
                ]);

                $this->result->updated++;
                Log::info('Product updated', [
                    'firestore_id' => $firestoreId,
                    'name' => $name,
                    'changes' => $changes,
                ]);
            }
        } else {
            // Create new product
            Product::create([
                'firestore_id' => $firestoreId,
                'category_id' => $category->id,
                'name' => $name,
                'slug' => Str::slug($name) . '-' . $firestoreId,
                'description' => $description,
                'price' => intval($price),
                'image' => $imageUrl,
                'is_active' => $isActive,
                'sort_order' => 1,
            ]);

            $this->result->created++;
            Log::info('Product created', [
                'firestore_id' => $firestoreId,
                'name' => $name,
            ]);
        }
    }

    /**
     * Detect changes between existing product and Firebase data
     */
    private function detectChanges(Product $product, array $fields, int $newCategoryId): array
    {
        $changes = [];

        if ($product->category_id !== $newCategoryId) {
            $changes['category_id'] = ['old' => $product->category_id, 'new' => $newCategoryId];
        }

        $name = $fields['name']['stringValue'] ?? '';
        if ($product->name !== $name) {
            $changes['name'] = ['old' => $product->name, 'new' => $name];
        }

        $price = $fields['price']['doubleValue'] ?? $fields['price']['integerValue'] ?? 0;
        if ($product->price !== intval($price)) {
            $changes['price'] = ['old' => $product->price, 'new' => intval($price)];
        }

        $description = $fields['description']['stringValue'] ?? '';
        if ($product->description !== $description) {
            $changes['description'] = ['old' => $product->description, 'new' => $description];
        }

        $imageUrl = $fields['imageUrl']['stringValue'] ?? null;
        if ($product->image !== $imageUrl) {
            $changes['image'] = ['old' => $product->image, 'new' => $imageUrl];
        }

        $isActive = $fields['isActive']['booleanValue'] ?? true;
        if ($product->is_active !== $isActive) {
            $changes['is_active'] = ['old' => $product->is_active, 'new' => $isActive];
        }

        return $changes;
    }

    /**
     * Delete products that are in PostgreSQL but not in Firebase
     */
    private function deleteObsoleteProducts(array $firestoreIds): void
    {
        // Delete products without firestore_id (should not exist, but just in case)
        $deletedWithoutId = Product::whereNull('firestore_id')->delete();
        if ($deletedWithoutId > 0) {
            $this->result->deleted += $deletedWithoutId;
            Log::info('Deleted products without firestore_id', ['count' => $deletedWithoutId]);
        }

        // Delete products that were removed from Firebase
        if (!empty($firestoreIds)) {
            $deletedObsolete = Product::whereNotNull('firestore_id')
                ->whereNotIn('firestore_id', $firestoreIds)
                ->delete();

            if ($deletedObsolete > 0) {
                $this->result->deleted += $deletedObsolete;
                Log::info('Deleted obsolete products', ['count' => $deletedObsolete]);
            }
        }
    }

    /**
     * Delete empty categories
     */
    private function deleteEmptyCategories(): void
    {
        $deletedCategories = Category::doesntHave('products')->delete();
        if ($deletedCategories > 0) {
            Log::info('Deleted empty categories', ['count' => $deletedCategories]);
        }
    }
}

/**
 * Sync result class
 */
class SyncResult
{
    public bool $success = false;
    public int $created = 0;
    public int $updated = 0;
    public int $deleted = 0;
    public ?string $error = null;
}
