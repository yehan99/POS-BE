<?php

namespace App\Http\Requests\Inventory\StockTransfer;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('received_items')) {
            $this->merge(['receivedItems' => $this->input('received_items')]);
        }

        $this->merge([
            'receivedBy' => $this->input('receivedBy', $this->input('received_by')),
            'notes' => $this->input('notes', $this->input('receive_notes')),
        ]);
    }

    public function rules(): array
    {
        return [
            'receivedItems' => ['required', 'array', 'min:1'],
            'receivedItems.*.itemId' => ['nullable', 'uuid'],
            'receivedItems.*.productId' => ['nullable', 'uuid'],
            'receivedItems.*.receivedQuantity' => ['required', 'integer', 'min:0'],
            'receivedBy' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
