<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $table = 'store_payment_settings';

    protected $fillable = [
        'store_id',
        'provider',
        'enabled',
        'mode',
        'public_key',
        'secret_key',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'public_key' => 'encrypted',
            'secret_key' => 'encrypted',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
