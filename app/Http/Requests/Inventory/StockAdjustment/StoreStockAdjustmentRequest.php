<?php

namespace App\Http\Requests\Inventory\StockAdjustment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'adjustmentNumber' => $this->input('adjustmentNumber', $this->input('adjustment_number')),
            'productId' => $this->input('productId', $this->input('product_id')),
            'adjustmentType' => $this->input('adjustmentType', $this->input('adjustment_type')),
            'locationId' => $this->input('locationId', $this->input('location_id')),
        ]);
    }

    public function rules(): array
    {
        return [
            'adjustmentNumber' => ['nullable', 'string', 'max:60'],
            'productId' => ['required', 'uuid', 'exists:products,id'],
            'adjustmentType' => ['required', 'string', Rule::in([
                'increase',
                'decrease',
                'damage',
                'loss',
                'found',
                'return',
                'correction',
            ])],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'locationId' => ['nullable', 'uuid', 'exists:inventory_locations,id'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
