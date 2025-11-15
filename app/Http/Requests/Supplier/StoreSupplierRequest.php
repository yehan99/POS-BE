<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
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
            'supplierCode' => ['nullable', 'string', 'max:50', 'unique:suppliers,supplier_code'],
            'name' => ['required', 'string', 'max:150'],
            'contactPerson' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', 'unique:suppliers,email'],
            'phone' => ['nullable', 'string', 'max:60', 'unique:suppliers,phone'],
            'category' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive,blocked'],
            'isActive' => ['sometimes', 'boolean'],
            'isPreferred' => ['sometimes', 'boolean'],
            'paymentTerms' => ['nullable', 'string', 'max:255'],
            'creditLimit' => ['nullable', 'numeric', 'min:0'],
            'taxId' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'address.postalCode' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'max:120'],
            'bankDetails' => ['nullable', 'array'],
            'bankDetails.bankName' => ['nullable', 'string', 'max:150'],
            'bankDetails.accountNumber' => ['nullable', 'string', 'max:120'],
            'bankDetails.accountName' => ['nullable', 'string', 'max:150'],
            'bankDetails.branchCode' => ['nullable', 'string', 'max:60'],
            'bankDetails.swiftCode' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'totalPurchases' => ['nullable', 'integer', 'min:0'],
            'totalOrders' => ['nullable', 'integer', 'min:0'],
            'totalSpent' => ['nullable', 'numeric', 'min:0'],
            'spendThisMonth' => ['nullable', 'numeric', 'min:0'],
            'spendLastMonth' => ['nullable', 'numeric', 'min:0'],
            'onTimeDeliveryRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'averageLeadTimeDays' => ['nullable', 'numeric', 'min:0'],
            'lastPurchaseDate' => ['nullable', 'date'],
            'monthlySpendStats' => ['nullable', 'array'],
            'monthlySpendStats.*.period' => ['required_with:monthlySpendStats', 'string', 'max:10'],
            'monthlySpendStats.*.totalSpend' => ['required_with:monthlySpendStats', 'numeric', 'min:0'],
            'monthlySpendStats.*.purchaseOrders' => ['required_with:monthlySpendStats', 'integer', 'min:0'],
            'monthlySpendStats.*.averageLeadTimeDays' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
