<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'description',
        'price',
        'duration_minutes',
        'image_path',
        'is_active',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
