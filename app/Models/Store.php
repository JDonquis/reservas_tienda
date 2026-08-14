<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Store extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'api_key',
        'google_access_token',
        'google_refresh_token',
        'google_calendar_id',
        'google_channel_id',
        'allowed_domain',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            if (empty($store->api_key)) {
                $store->api_key = self::generateApiKey();
            }
        });
    }

    public static function generateApiKey(): string
    {
        return 'sk_'.Str::uuid();
    }
}
