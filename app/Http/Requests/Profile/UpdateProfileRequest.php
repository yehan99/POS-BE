<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->input('firstName', $this->input('first_name')),
            'last_name' => $this->input('lastName', $this->input('last_name')),
            'phone' => $this->input('phone'),
            'avatar_url' => $this->input('avatarUrl', $this->input('avatar_url')),
            'job_title' => $this->input('jobTitle', $this->input('job_title')),
            'department' => $this->input('department'),
            'bio' => $this->input('bio'),
            'preferences' => $this->input('preferences'),
            'site_id' => $this->input('siteId', $this->input('site_id')),
            'role_id' => $this->input('roleId', $this->input('role_id')),
            'email' => $this->input('email'),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:25'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'preferences' => ['nullable', 'array'],
            'preferences.timezone' => ['nullable', 'string', 'max:120'],
            'preferences.language' => ['nullable', 'string', 'max:12'],
            'preferences.theme' => ['nullable', Rule::in(['system', 'light', 'dark'])],
            'preferences.digestEmails' => ['nullable', 'boolean'],
            'preferences.notifications' => ['nullable', 'array'],
            'preferences.notifications.newOrders' => ['nullable', 'boolean'],
            'preferences.notifications.lowStock' => ['nullable', 'boolean'],
            'preferences.notifications.productUpdates' => ['nullable', 'boolean'],
            'site_id' => ['prohibited'],
            'role_id' => ['prohibited'],
            'email' => ['prohibited'],
        ];
    }
}
