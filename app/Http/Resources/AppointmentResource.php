<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'service' => $this->whenLoaded('service', function () {
                return [
                    'id' => $this->service->id,
                    'name' => $this->service->name,
                    'price' => (float) $this->service->price,
                    'duration_minutes' => (int) $this->service->duration_minutes,
                ];
            }),
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'google_linked' => (bool) $this->google_event_id,
        ];
    }
}
