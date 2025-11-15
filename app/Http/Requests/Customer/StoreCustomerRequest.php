<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'customerCode' => ['nullable', 'string', 'max:50', 'unique:customers,customer_code'],
            'firstName' => ['required', 'string', 'max:120'],
            'lastName' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:50', 'unique:customers,phone'],
            'dateOfBirth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'address.postalCode' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'max:120'],
            'loyaltyPoints' => ['nullable', 'integer', 'min:0'],
            'loyaltyTier' => ['nullable', 'in:bronze,silver,gold,platinum'],
            'notes' => ['nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
