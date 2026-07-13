<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data,
            'is_read' => (bool) $this->is_read,
            'created_at' => $this->created_at->toDateTimeString(),
            'order' => $this->whenLoaded('order'),
        ];
    }
}
