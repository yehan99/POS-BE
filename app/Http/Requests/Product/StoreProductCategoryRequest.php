<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string'],
            'parentId' => ['nullable', 'uuid', 'exists:product_categories,id'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
            'image' => ['nullable', 'string', 'max:255'],
            'tenantId' => ['nullable', 'ulid', 'exists:tenants,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
