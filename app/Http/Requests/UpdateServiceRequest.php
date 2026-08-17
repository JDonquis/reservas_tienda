<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:categories,id',
            'price' => 'sometimes|required|numeric|min:0',
            'duration_minutes' => 'sometimes|required|integer|min:1',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ];
    }
}
