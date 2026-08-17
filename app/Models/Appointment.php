<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'store_id',
        'service_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'start_time',
        'end_time',
        'google_event_id',
        'status',
        'payment_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
