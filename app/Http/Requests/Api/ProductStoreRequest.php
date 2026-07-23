<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProductStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'firestore_id' => ['nullable', 'string', 'max:255'],
            'cost_price' => ['nullable', 'integer', 'min:0'],
            'stock' => ['nullable', 'integer'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'image' => $this->hasFile('image')
                ? ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120']
                : ['nullable', 'url', 'max:2048'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
