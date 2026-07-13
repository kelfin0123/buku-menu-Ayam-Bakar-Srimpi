<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'integer', 'min:0'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'firestore_id' => ['nullable', 'string', 'max:255'],
            'image' => $this->hasFile('image')
                ? ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120']
                : ['nullable', 'url', 'max:2048'],
        ];
    }
}
