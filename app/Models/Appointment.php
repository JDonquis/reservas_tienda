<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'store_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'start_time',
        'end_time',
        'google_event_id',
        'status',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
