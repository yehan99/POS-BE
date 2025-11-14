<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * Remove the "data" wrapper so the payload matches the frontend contract.
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('role.permissions', 'permissions', 'tenant', 'site');

        return [
            'id' => $this->id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'isActive' => (bool) $this->is_active,
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'status' => $this->is_active ? 'active' : 'inactive',
            'roleId' => $this->role_id,
            'siteId' => $this->site_id,
            'siteCode' => $this->site?->slug,
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
            'site' => $this->when($this->site, function () {
                return [
                    'id' => $this->site->id,
                    'name' => $this->site->name,
                    'slug' => $this->site->slug,
                    'description' => $this->site->description,
                ];
            }),
            'permissions' => $this->resource->permissionSlugs()->values()->all(),
            'metadata' => $this->metadata,
        ];
    }
}
