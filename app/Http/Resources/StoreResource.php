<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'api_key' => $this->api_key,
            'allowed_domain' => $this->allowed_domain,
            'google_linked' => (bool) $this->google_access_token,
            'created_at' => $this->created_at?->toIso8601String(),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),
        ];
    }
}
