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
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'google_linked' => (bool) $this->google_event_id,
        ];
    }
}
