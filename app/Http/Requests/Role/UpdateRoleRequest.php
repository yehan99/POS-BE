<?php

namespace App\Http\Requests\Role;

use App\Support\Permissions\PermissionMatrix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'slug')],
            'matrix' => ['sometimes', 'array'],
            'matrix.*.key' => ['required_with:matrix', 'string'],
            'matrix.*.view' => ['sometimes', 'boolean'],
            'matrix.*.write' => ['sometimes', 'boolean'],
            'matrix.*.delete' => ['sometimes', 'boolean'],
        ];
    }

    public function validatedPermissions(): array
    {
        if ($this->filled('matrix')) {
            return PermissionMatrix::slugsFromMatrix($this->input('matrix', []));
        }

        return array_values(array_unique($this->input('permissions', [])));
    }

    public function validatedPart(string ...$keys): array
    {
        return $this->safe()->only($keys);
    }
}
