<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;
use Throwable;

class FirebaseProductSyncService
{
    public function __construct(
        private readonly FirestoreProductService $firestore = new FirestoreProductService,
    ) {}

    public function sync(bool $dryRun = false): SyncResult
    {
        $result = new SyncResult;

        try {
            foreach ($this->firestore->getRawDocuments() as $document) {
                try {
                    $firestoreId = basename((string) ($document['name'] ?? ''));
                    if ($firestoreId === '') {
                        $result->skipped++;

                        continue;
                    }

                    $this->syncProduct(
                        $firestoreId,
                        $document['fields'] ?? [],
                        $dryRun,
                        $result,
                    );
                } catch (Throwable) {
                    $result->failed++;
                }
            }

            $result->success = $result->failed === 0;
        } catch (Throwable $e) {
            $result->success = false;
            $result->error = $e->getMessage();
        }

        return $result;
    }

    private function syncProduct(
        string $firestoreId,
        array $fields,
        bool $dryRun,
        SyncResult $result,
    ): void {
        $data = [
            'name' => $this->stringValue($fields, 'name', 'Produk tanpa nama'),
            'description' => $this->stringValue($fields, 'description'),
            'category' => $this->stringValue($fields, 'category', 'Umum'),
            'price' => $this->integerValue($fields, 'price'),
            'cost_price' => $this->integerValue($fields, 'costPrice'),
            'stock' => $this->integerValue($fields, 'stock'),
            'minimum_stock' => $this->integerValue($fields, 'minimumStock', 5),
            'barcode' => $this->stringValue($fields, 'barcode'),
            'image_url' => $this->nullableStringValue($fields, 'imageUrl'),
            'is_active' => $this->booleanValue($fields, 'isActive', true),
        ];

        $existing = Product::with('category')
            ->where('firestore_id', $firestoreId)
            ->first();
        $changed = $existing === null
            || $existing->name !== $data['name']
            || (string) $existing->description !== $data['description']
            || $existing->category?->name !== $data['category']
            || (int) $existing->price !== $data['price']
            || (int) $existing->cost_price !== $data['cost_price']
            || (int) $existing->stock !== $data['stock']
            || (int) $existing->minimum_stock !== $data['minimum_stock']
            || (string) $existing->barcode !== $data['barcode']
            || $existing->getRawOriginal('image_url') !== $data['image_url']
            || (bool) $existing->is_active !== $data['is_active'];

        if ($dryRun) {
            if ($existing === null) {
                $result->created++;
            } elseif ($changed) {
                $result->updated++;
            } else {
                $result->skipped++;
            }

            return;
        }

        if (! $changed) {
            $result->skipped++;

            return;
        }

        $category = Category::firstOrCreate(
            ['name' => $data['category']],
            [
                'slug' => Str::slug($data['category']),
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Product::updateOrCreate(
            ['firestore_id' => $firestoreId],
            [
                'category_id' => $category->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.substr(sha1($firestoreId), 0, 12),
                'description' => $data['description'],
                'price' => $data['price'],
                'cost_price' => $data['cost_price'],
                'stock' => $data['stock'],
                'minimum_stock' => $data['minimum_stock'],
                'barcode' => $data['barcode'],
                'image_url' => $data['image_url'],
                'is_active' => $data['is_active'],
                'sort_order' => 1,
            ],
        );

        $existing === null ? $result->created++ : $result->updated++;
    }

    private function rawValue(array $fields, string $field): mixed
    {
        $encoded = $fields[$field] ?? [];

        return $encoded['stringValue']
            ?? $encoded['integerValue']
            ?? $encoded['doubleValue']
            ?? $encoded['booleanValue']
            ?? null;
    }

    private function stringValue(array $fields, string $field, string $default = ''): string
    {
        return (string) ($this->rawValue($fields, $field) ?? $default);
    }

    private function nullableStringValue(array $fields, string $field): ?string
    {
        $value = $this->rawValue($fields, $field);

        return $value === null || $value === '' ? null : (string) $value;
    }

    private function integerValue(array $fields, string $field, int $default = 0): int
    {
        return (int) ($this->rawValue($fields, $field) ?? $default);
    }

    private function booleanValue(array $fields, string $field, bool $default): bool
    {
        return (bool) ($this->rawValue($fields, $field) ?? $default);
    }
}

class SyncResult
{
    public bool $success = false;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $failed = 0;

    public ?string $error = null;
}
