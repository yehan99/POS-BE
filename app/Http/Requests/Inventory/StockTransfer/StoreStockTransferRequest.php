<?php

namespace App\Http\Requests\Inventory\StockTransfer;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fromLocationId' => $this->input('fromLocationId', $this->input('from_location_id')),
            'toLocationId' => $this->input('toLocationId', $this->input('to_location_id')),
            'requestedBy' => $this->input('requestedBy', $this->input('requested_by')),
        ]);
    }

    public function rules(): array
    {
        return [
            'transferNumber' => ['nullable', 'string', 'max:60'],
            'fromLocationId' => ['required', 'uuid', 'different:toLocationId', 'exists:inventory_locations,id'],
            'toLocationId' => ['required', 'uuid', 'different:fromLocationId', 'exists:inventory_locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'requestedBy' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['nullable', 'uuid', 'exists:products,id'],
            'items.*.productName' => ['required', 'string', 'max:200'],
            'items.*.productSku' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unitCost' => ['nullable', 'numeric', 'min:0'],
            'items.*.totalCost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
