<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tenantInput = $this->input('tenant', []);

        $this->merge([
            'first_name' => $this->input('firstName', $this->input('first_name')),
            'last_name' => $this->input('lastName', $this->input('last_name')),
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'password' => $this->input('password'),
            'device_name' => $this->input('deviceName', $this->input('device_name')),
            'tenant' => [
                'name' => $tenantInput['name'] ?? null,
                'business_type' => $tenantInput['businessType'] ?? $tenantInput['business_type'] ?? null,
                'country' => $tenantInput['country'] ?? null,
                'phone' => $tenantInput['phone'] ?? null,
                'settings' => $tenantInput['settings'] ?? null,
            ],
            'role_slug' => $this->input('roleSlug', $this->input('role_slug', 'admin')),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'device_name' => ['nullable', 'string', 'max:120'],
            'role_slug' => ['required', 'string'],
            'tenant.name' => ['required', 'string', 'max:255'],
            'tenant.business_type' => ['required', 'string', 'max:60'],
            'tenant.country' => ['required', 'string', 'size:2'],
            'tenant.phone' => ['nullable', 'string', 'max:25'],
            'tenant.settings' => ['nullable', 'array'],
        ];
    }
}
