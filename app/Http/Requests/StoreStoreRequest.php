<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'allowed_domain' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
        ];
    }
}
