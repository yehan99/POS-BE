<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerLoyaltyTransactionRequest extends FormRequest
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
            'type' => ['required', 'in:earned,redeemed,adjusted'],
            'pointsDelta' => ['required', 'integer'],
            'totalSpentDelta' => ['nullable', 'numeric'],
            'purchasesDelta' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
