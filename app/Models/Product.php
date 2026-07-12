<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'is_promo',
        'is_active',
        'sort_order',
        'firestore_id',
    ];

    protected $casts = [
        'is_promo'  => 'boolean',
        'is_active' => 'boolean',
        'price'     => 'integer',
        'promo_price' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Harga final yang dipakai (promo jika ada) */
    public function getFinalPriceAttribute(): int
    {
        return $this->is_promo && $this->promo_price
            ? $this->promo_price
            : $this->price;
    }

    /** Path gambar publik dengan fallback placeholder */
    public function getImageUrlAttribute(): string
    {
        if ($this->image && (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://'))) {
            return $this->image;
        }

        return $this->image
            ? asset('images/products/' . $this->image)
            : asset('images/products/placeholder.jpg');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
