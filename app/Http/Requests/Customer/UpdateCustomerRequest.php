<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
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
        $customerId = $this->route('customer');

        return [
            'customerCode' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('customers', 'customer_code')->ignore($customerId),
            ],
            'firstName' => ['sometimes', 'required', 'string', 'max:120'],
            'lastName' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('customers', 'email')->ignore($customerId),
            ],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'phone')->ignore($customerId),
            ],
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
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
