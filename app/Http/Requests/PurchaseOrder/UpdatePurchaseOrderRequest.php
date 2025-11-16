<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplierId' => ['sometimes', 'uuid', 'exists:suppliers,id'],
            'status' => ['sometimes', 'string', 'max:40'],
            'paymentStatus' => ['sometimes', 'string', 'max:40'],
            'paymentMethod' => ['sometimes', 'string', 'max:80'],
            'expectedDeliveryDate' => ['sometimes', 'nullable', 'date'],
            'actualDeliveryDate' => ['sometimes', 'nullable', 'date'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'shippingCost' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'termsAndConditions' => ['sometimes', 'nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'uuid'],
            'items.*.productId' => ['nullable', 'uuid', 'exists:products,id'],
            'items.*.productName' => ['required_with:items', 'string', 'max:200'],
            'items.*.productSku' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.receivedQuantity' => ['nullable', 'integer', 'min:0'],
            'items.*.unitCost' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.tax' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('supplierId')) {
            $this->merge([
                'supplier_id' => $this->input('supplierId'),
            ]);
        }
    }
}
