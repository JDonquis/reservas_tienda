<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'store_id',
        'provider',
        'provider_payment_id',
        'status',
        'amount',
        'currency',
        'payable_type',
        'payable_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'metadata' => 'array',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function payable()
    {
        return $this->morphTo();
    }
}
