<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:superadmin,store_owner',
            'store_id' => 'nullable|integer|exists:stores,id',
            'store' => 'nullable|array',
            'store.name' => 'required_with:store|string|max:255',
            'store.allowed_domain' => 'nullable|string|max:255',
        ];
    }
}
