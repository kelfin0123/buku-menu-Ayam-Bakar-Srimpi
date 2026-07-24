<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'promo_price',
        'image',
        'image_url',
        'cost_price',
        'stock',
        'minimum_stock',
        'barcode',
        'is_promo',
        'is_active',
        'sort_order',
        'firestore_id',
    ];

    protected $casts = [
        'is_promo' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'integer',
        'stock' => 'integer',
        'cost_price' => 'integer',
        'minimum_stock' => 'integer',
        'promo_price' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    /** Harga final yang dipakai (promo jika ada) */
    public function getFinalPriceAttribute(): int
    {
        return $this->is_promo && $this->promo_price
            ? $this->promo_price
            : $this->price;
    }

    /** Path gambar publik dengan fallback placeholder */
    public function getImageUrlAttribute(?string $value): string
    {
        if ($value) {
            return $value;
        }

        if ($this->image && (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://'))) {
            return $this->image;
        }

        if ($this->image) {
            return url(Storage::url($this->image));
        }

        return asset('images/no-image.png');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
