<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProductCategoriesRequest extends FormRequest
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
            'categoryIds' => ['required', 'array', 'min:1'],
            'categoryIds.*' => ['uuid', 'exists:product_categories,id'],
        ];
    }
}
