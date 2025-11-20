<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions\PermissionMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $roles = Role::query()
            ->with(['permissions'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles);
    }

    public function show(Request $request, Role $role): RoleResource
    {
        $this->ensureSuperAdmin($request);

        $role->loadMissing('permissions');

        return new RoleResource($role);
    }

    public function modules(Request $request): JsonResponse
    {
        $this->ensureSuperAdmin($request);

        return response()->json([
            'modules' => PermissionMatrix::modulesForResponse(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $this->ensureSuperAdmin($request);

        $role->fill($request->validatedPart('name', 'description'));
        $role->save();

        $permissions = $request->validatedPermissions();

        if (!empty($permissions)) {
            $permissionIds = Permission::query()
                ->whereIn('slug', $permissions)
                ->pluck('id');
            $role->permissions()->sync($permissionIds);
        } else {
            $role->permissions()->sync([]);
        }

        $role->load('permissions');

        return new RoleResource($role);
    }

    private function ensureSuperAdmin(Request $request): void
    {
        $user = $request->user();

        if (!$user || $user->role?->slug !== 'super_admin') {
            abort(403, 'Only super administrators can manage roles.');
        }
    }
}
