<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('role.permissions', 'permissions', 'tenant');

        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'isActive' => (bool) $this->is_active,
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'tenant' => $this->when($this->tenant, function () {
                return [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                    'businessType' => $this->tenant->business_type,
                    'country' => $this->tenant->country,
                    'phone' => $this->tenant->phone,
                    'settings' => $this->tenant->settings,
                ];
            }),
            'role' => $this->when($this->role, function () {
                return [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                    'slug' => $this->role->slug,
                ];
            }),
            'permissions' => $this->resource->permissionSlugs()->values()->all(),
            'metadata' => $this->metadata,
        ];
    }
}
