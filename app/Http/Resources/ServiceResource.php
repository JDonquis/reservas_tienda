<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'duration_minutes' => (int) $this->duration_minutes,
            'image_url' => $this->image_path ? asset('storage/'.$this->image_path) : null,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
