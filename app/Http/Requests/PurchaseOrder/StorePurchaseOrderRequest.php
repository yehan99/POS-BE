<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
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
            'supplierId' => ['required', 'uuid', 'exists:suppliers,id'],
            'status' => ['nullable', 'string', 'max:40'],
            'paymentStatus' => ['nullable', 'string', 'max:40'],
            'paymentMethod' => ['nullable', 'string', 'max:80'],
            'expectedDeliveryDate' => ['nullable', 'date'],
            'actualDeliveryDate' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'shippingCost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'termsAndConditions' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['nullable', 'uuid', 'exists:products,id'],
            'items.*.productName' => ['required', 'string', 'max:200'],
            'items.*.productSku' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unitCost' => ['required', 'numeric', 'min:0'],
            'items.*.tax' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'supplier_id' => $this->input('supplierId'),
        ]);
    }
}
