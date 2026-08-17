<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'offer_price' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ];
    }
}
