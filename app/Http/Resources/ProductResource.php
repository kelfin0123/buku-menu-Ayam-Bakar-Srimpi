<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'category_id' => $this->category_id,
            'description' => $this->description,
            'image' => $this->image, // path stored in DB
            'image_url' => $this->image_url,
            'imageUrl' => $this->image_url,
            'firestore_id' => $this->firestore_id,
            'cost_price' => (int) $this->cost_price,
            'costPrice' => (int) $this->cost_price,
            'stock' => (int) $this->stock,
            'minimum_stock' => (int) $this->minimum_stock,
            'minimumStock' => (int) $this->minimum_stock,
            'barcode' => (string) $this->barcode,
            'is_active' => (bool) $this->is_active,
            'isActive' => (bool) $this->is_active,
            'is_promo' => (bool) $this->is_promo,
        ];
    }
}
