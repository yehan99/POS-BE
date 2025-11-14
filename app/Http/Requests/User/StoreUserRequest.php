<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorBase;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission('user.management');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->input('firstName', $this->input('first_name')),
            'last_name' => $this->input('lastName', $this->input('last_name')),
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'role_id' => $this->input('roleId', $this->input('role_id')),
            'site_id' => $this->input('siteId', $this->input('site_id')),
            'is_active' => $this->input('isActive', $this->input('is_active', true)),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:25'],
            'role_id' => ['required', 'string', Rule::exists('roles', 'id')],
            'site_id' => ['required', 'string', Rule::exists('sites', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Return custom messages with camelCase keys to match frontend expectations.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role_id.required' => 'Please select a role.',
            'role_id.exists' => 'The selected role is invalid.',
            'site_id.required' => 'Please select a site.',
            'site_id.exists' => 'The selected site is invalid.',
        ];
    }

    /**
     * Transform validation errors to use camelCase keys for frontend.
     *
     * @param  Validator  $validator
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = [];
        foreach ($validator->errors()->toArray() as $field => $messages) {
            // Convert snake_case field names to camelCase for frontend compatibility
            $camelCase = match ($field) {
                'role_id' => 'roleId',
                'site_id' => 'siteId',
                'site_code' => 'siteCode',
                'first_name' => 'firstName',
                'last_name' => 'lastName',
                'is_active' => 'isActive',
                default => $field,
            };
            $errors[$camelCase] = $messages;
        }

        throw new HttpResponseException(
            response()->json(['errors' => $errors], 422)
        );
    }
}
