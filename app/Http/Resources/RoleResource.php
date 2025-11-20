<?php

namespace App\Http\Resources;

use App\Support\Permissions\PermissionMatrix;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Role
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $permissions = $this->whenLoaded('permissions', fn () => $this->permissions->pluck('slug')) ?? collect();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'isDefault' => (bool) $this->is_default,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'permissions' => $permissions,
            'matrix' => PermissionMatrix::summarize($permissions),
        ];
    }
}
