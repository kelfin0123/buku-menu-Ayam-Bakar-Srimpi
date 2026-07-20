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
        $projectId = config('firebase.project_id', config('services.firebase.project_id', 'kasir-40363'));
        $collection = config('firebase.products_collection', config('services.firebase.products_collection', 'products'));
        $defaultUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}";

        $this->firebaseUrl = config('firebase.products_url', config('services.firebase.products_url', $defaultUrl));
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
        $name = $this->getStringValue($fields, 'name');
        $price = $this->getNumericValue($fields, 'price');
        $categoryName = $this->getStringValue($fields, 'category', 'stringValue', 'Umum');
        $description = $this->getStringValue($fields, 'description');
        $imageUrl = $this->getStringValue($fields, 'imageUrl', 'stringValue', null);
        $isActive = $this->getBooleanValue($fields, 'isActive', true);
        $stock = $this->getIntegerValue($fields, 'stock', 0);
        $minimumStock = $this->getIntegerValue($fields, 'minimumStock', 5);
        $barcode = $this->getStringValue($fields, 'barcode');

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
                    'stock' => $stock,
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
                'stock' => $stock,
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

        $name = $this->getStringValue($fields, 'name');
        if ($product->name !== $name) {
            $changes['name'] = ['old' => $product->name, 'new' => $name];
        }

        $price = $this->getNumericValue($fields, 'price');
        if ($product->price !== intval($price)) {
            $changes['price'] = ['old' => $product->price, 'new' => intval($price)];
        }

        $description = $this->getStringValue($fields, 'description');
        if ($product->description !== $description) {
            $changes['description'] = ['old' => $product->description, 'new' => $description];
        }

        $imageUrl = $this->getStringValue($fields, 'imageUrl', 'stringValue', null);
        if ($product->image !== $imageUrl) {
            $changes['image'] = ['old' => $product->image, 'new' => $imageUrl];
        }

        $isActive = $this->getBooleanValue($fields, 'isActive', true);
        if ($product->is_active !== $isActive) {
            $changes['is_active'] = ['old' => $product->is_active, 'new' => $isActive];
        }

        $stock = $this->getIntegerValue($fields, 'stock', 0);
        if ($product->stock !== $stock) {
            $changes['stock'] = ['old' => $product->stock, 'new' => $stock];
        }

        return $changes;
    }

    private function getStringValue(array $fields, string $field, string $valueKey = 'stringValue', ?string $default = ''): ?string
    {
        $value = $fields[$field][$valueKey] ?? null;

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function getBooleanValue(array $fields, string $field, bool $default = true): bool
    {
        $value = $fields[$field]['booleanValue'] ?? null;

        if ($value === null) {
            return $default;
        }

        return (bool) $value;
    }

    private function getIntegerValue(array $fields, string $field, int $default = 0): int
    {
        $value = $fields[$field]['integerValue'] ?? null;

        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }

    private function getNumericValue(array $fields, string $field): float|int
    {
        $value = $fields[$field]['doubleValue'] ?? $fields[$field]['integerValue'] ?? null;

        if ($value === null) {
            return 0;
        }

        return is_string($value) ? (float) $value : $value;
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
