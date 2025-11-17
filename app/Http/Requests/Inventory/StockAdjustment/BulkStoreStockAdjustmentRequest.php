<?php

namespace App\Http\Requests\Inventory\StockAdjustment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $adjustments = $this->input('adjustments', $this->input('data', []));

        if (! is_array($adjustments)) {
            $adjustments = [];
        }

        $normalized = array_map(function ($adjustment) {
            if (! is_array($adjustment)) {
                return [];
            }

            return [
                'adjustmentNumber' => $adjustment['adjustmentNumber'] ?? $adjustment['adjustment_number'] ?? null,
                'productId' => $adjustment['productId'] ?? $adjustment['product_id'] ?? null,
                'adjustmentType' => $adjustment['adjustmentType'] ?? $adjustment['adjustment_type'] ?? null,
                'quantity' => $adjustment['quantity'] ?? null,
                'reason' => $adjustment['reason'] ?? null,
                'notes' => $adjustment['notes'] ?? null,
                'locationId' => $adjustment['locationId'] ?? $adjustment['location_id'] ?? null,
                'meta' => $adjustment['meta'] ?? null,
            ];
        }, $adjustments);

        $this->merge([
            'adjustments' => $normalized,
        ]);
    }

    public function rules(): array
    {
        return [
            'adjustments' => ['required', 'array', 'min:1'],
            'adjustments.*.adjustmentNumber' => ['nullable', 'string', 'max:60'],
            'adjustments.*.productId' => ['required', 'uuid', 'exists:products,id'],
            'adjustments.*.adjustmentType' => ['required', 'string', Rule::in([
                'increase',
                'decrease',
                'damage',
                'loss',
                'found',
                'return',
                'correction',
            ])],
            'adjustments.*.quantity' => ['required', 'integer', 'min:1'],
            'adjustments.*.reason' => ['required', 'string', 'max:255'],
            'adjustments.*.notes' => ['nullable', 'string', 'max:2000'],
            'adjustments.*.locationId' => ['nullable', 'uuid', 'exists:inventory_locations,id'],
            'adjustments.*.meta' => ['nullable', 'array'],
        ];
    }
}
