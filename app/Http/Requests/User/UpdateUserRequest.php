<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'is_active' => $this->input('isActive', $this->input('is_active')),
        ]);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->getKey();

        return [
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:25'],
            'role_id' => ['sometimes', 'string', Rule::exists('roles', 'id')],
            'site_id' => ['sometimes', 'string', Rule::exists('sites', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
