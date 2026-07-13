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
            'is_active' => (bool) $this->is_active,
            'is_promo' => (bool) $this->is_promo,
        ];
    }
}
