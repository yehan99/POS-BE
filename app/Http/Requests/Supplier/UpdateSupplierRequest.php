<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
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
        $supplierId = $this->route('supplier')?->id ?? $this->route('supplier');

        return [
            'supplierCode' => ['sometimes', 'string', 'max:50', 'unique:suppliers,supplier_code,' . $supplierId],
            'name' => ['sometimes', 'string', 'max:150'],
            'contactPerson' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', 'unique:suppliers,email,' . $supplierId],
            'phone' => ['sometimes', 'nullable', 'string', 'max:60', 'unique:suppliers,phone,' . $supplierId],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive,blocked'],
            'isActive' => ['sometimes', 'boolean'],
            'isPreferred' => ['sometimes', 'boolean'],
            'paymentTerms' => ['sometimes', 'nullable', 'string', 'max:255'],
            'creditLimit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'taxId' => ['sometimes', 'nullable', 'string', 'max:120'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'address.postalCode' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'max:120'],
            'bankDetails' => ['sometimes', 'nullable', 'array'],
            'bankDetails.bankName' => ['nullable', 'string', 'max:150'],
            'bankDetails.accountNumber' => ['nullable', 'string', 'max:120'],
            'bankDetails.accountName' => ['nullable', 'string', 'max:150'],
            'bankDetails.branchCode' => ['nullable', 'string', 'max:60'],
            'bankDetails.swiftCode' => ['nullable', 'string', 'max:60'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'rating' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:5'],
            'totalPurchases' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'totalOrders' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'totalSpent' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'spendThisMonth' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'spendLastMonth' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'onTimeDeliveryRate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'averageLeadTimeDays' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lastPurchaseDate' => ['sometimes', 'nullable', 'date'],
            'monthlySpendStats' => ['sometimes', 'nullable', 'array'],
            'monthlySpendStats.*.period' => ['required_with:monthlySpendStats', 'string', 'max:10'],
            'monthlySpendStats.*.totalSpend' => ['required_with:monthlySpendStats', 'numeric', 'min:0'],
            'monthlySpendStats.*.purchaseOrders' => ['required_with:monthlySpendStats', 'integer', 'min:0'],
            'monthlySpendStats.*.averageLeadTimeDays' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
