<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HeroBanner extends Model
{
    protected $fillable = [
        'title', 'highlight_text', 'subtitle', 'description', 'button_text',
        'button_url', 'image', 'image_url', 'background_image', 'is_active',
        'sort_order', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected $appends = ['resolved_image_url'];

    public function getResolvedImageUrlAttribute(): ?string
    {
        if ($this->image) {
            return url(Storage::disk('public')->url($this->image));
        }

        return $this->image_url;
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
