<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        if (isset($payload['category']) && is_array($payload['category'])) {
            $payload['categoryId'] = $payload['category']['id'] ?? null;
        }

        if (isset($payload['taxClass']) && is_array($payload['taxClass'])) {
            $payload['taxClassData'] = $payload['taxClass'];
        }

        $this->replace($payload);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'tenantId' => ['nullable', 'ulid', 'exists:tenants,id'],
            'categoryId' => ['nullable', 'uuid', 'exists:product_categories,id'],
            'sku' => [
                'sometimes',
                'string',
                'max:80',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('products', 'sku')->ignore($productId)->whereNull('deleted_at'),
            ],
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'string', 'max:120'],
            'barcode' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('products', 'barcode')->ignore($productId)->whereNull('deleted_at'),
            ],
            'price' => ['nullable', 'numeric', 'min:0'],
            'costPrice' => ['nullable', 'numeric', 'min:0'],
            'taxClassData' => ['nullable', 'array'],
            'isActive' => ['nullable', 'boolean'],
            'trackInventory' => ['nullable', 'boolean'],
            'stockQuantity' => ['nullable', 'integer', 'min:0'],
            'reorderLevel' => ['nullable', 'integer', 'min:0'],
            'maxStockLevel' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'dimensions.height' => ['nullable', 'numeric', 'min:0'],
            'dimensions.unit' => ['nullable', 'string', 'max:10'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string'],
            'variants' => ['nullable', 'array'],
            'attributes' => ['nullable', 'array'],
            'attributes.*.name' => ['required_with:attributes', 'string', 'max:120'],
            'attributes.*.value' => ['nullable', 'string', 'max:255'],
            'attributes.*.type' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
