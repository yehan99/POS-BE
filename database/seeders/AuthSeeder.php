<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthSeeder extends Seeder
{
    /**
     * Seed roles, permissions, and a default super admin user.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $permissions = $this->seedPermissions();
            $roles = $this->seedRoles($permissions);
            $this->seedSuperAdmin($roles['super_admin']);
            $this->promoteRequestedSuperAdmins($roles['super_admin']);
        });
    }

    /**
     * Seed permission catalog based on the frontend enum definition.
     *
     * @return array<string, Permission>
     */
    private function seedPermissions(): array
    {
        $definitions = config('permission-modules.permission_definitions', []);

        $records = [];

        foreach ($definitions as $slug => $definition) {
            $records[$slug] = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'module' => $definition['module'] ?? 'general',
                    'description' => Arr::get($definition, 'description'),
                ]
            );
        }

        return $records;
    }

    /**
     * Seed the base roles and attach relevant permissions.
     *
     * @param array<string, Permission> $permissions
     * @return array<string, Role>
     */
    private function seedRoles(array $permissions): array
    {
        $roleMatrix = [
            'super_admin' => [
                'name' => 'Super Admin',
                'description' => 'Full system access with tenant ownership rights.',
                'is_default' => false,
                'permissions' => array_keys($permissions),
            ],
            'admin' => [
                'name' => 'Admin',
                'description' => 'Manage business configuration and daily operations.',
                'is_default' => true,
                'permissions' => [
                    'product.create', 'product.read', 'product.update', 'product.delete',
                    'sale.create', 'sale.read', 'sale.refund', 'sale.void',
                    'inventory.create', 'inventory.read', 'inventory.update', 'inventory.adjust',
                    'customer.create', 'customer.read', 'customer.update', 'customer.delete',
                    'customer.loyalty.read', 'customer.loyalty.record',
                    'report.sales', 'report.inventory', 'report.customers', 'report.financial',
                    'settings.read', 'settings.update', 'user.management',
                ],
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Oversee frontline operations and approve adjustments.',
                'is_default' => false,
                'permissions' => [
                    'product.create', 'product.read', 'product.update',
                    'sale.create', 'sale.read', 'sale.refund',
                    'inventory.create', 'inventory.read', 'inventory.update', 'inventory.adjust',
                    'customer.create', 'customer.read', 'customer.update',
                    'customer.loyalty.read', 'customer.loyalty.record',
                    'report.sales', 'report.inventory', 'report.customers',
                    'settings.read',
                ],
            ],
            'cashier' => [
                'name' => 'Cashier',
                'description' => 'Perform point-of-sale operations and assist customers.',
                'is_default' => false,
                'permissions' => [
                    'sale.create', 'sale.read',
                    'customer.create', 'customer.read',
                    'customer.loyalty.read', 'customer.loyalty.record',
                    'report.sales',
                ],
            ],
            'viewer' => [
                'name' => 'Viewer',
                'description' => 'Read-only access to analytics and catalog data.',
                'is_default' => false,
                'permissions' => [
                    'product.read',
                    'sale.read',
                    'inventory.read',
                    'customer.read', 'customer.loyalty.read',
                    'report.sales', 'report.inventory', 'report.customers',
                ],
            ],
        ];

        $roles = [];

        foreach ($roleMatrix as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_default' => $definition['is_default'],
                ]
            );

            $permissionIds = collect($definition['permissions'])
                ->map(fn (string $slug) => $permissions[$slug]->id)
                ->all();

            $role->permissions()->sync($permissionIds);
            $roles[$slug] = $role;
        }

        return $roles;
    }

    /**
     * Seed a default tenant and super admin user account.
     */
    private function seedSuperAdmin(Role $superAdminRole): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['name' => 'Paradise POS HQ'],
            [
                'business_type' => 'retail',
                'country' => 'LK',
                'phone' => '+94 11 234 5678',
                'settings' => [
                    'currency' => 'LKR',
                    'timezone' => 'Asia/Colombo',
                    'language' => 'en',
                    'tax_settings' => [
                        'defaultTaxRate' => 15,
                        'taxInclusive' => true,
                    ],
                ],
            ]
        );

        $user = User::withTrashed()->updateOrCreate(
            ['email' => 'founder@paradisepos.com'],
            [
                'tenant_id' => $tenant->id,
                'role_id' => $superAdminRole->id,
                'first_name' => 'System',
                'last_name' => 'Owner',
                'phone' => '+94 77 123 4567',
                'password' => Hash::make('Password@123'),
                'is_active' => true,
                'email_verified_at' => now(),
                'metadata' => [
                    'provisioned_by' => 'seeder',
                    'notes' => 'Initial super admin user created by AuthSeeder',
                ],
                'remember_token' => Str::random(20),
            ]
        );

        if ($user->trashed()) {
            $user->restore();
        }
    }

    private function promoteRequestedSuperAdmins(Role $superAdminRole): void
    {
        $tenantId = Tenant::query()->value('id');

        if (! $tenantId) {
            return;
        }

        $this->promoteUserToSuperAdmin($superAdminRole, $tenantId, '1yehankalhara@gmail.com', [
            'first_name' => 'Yehan',
            'last_name' => 'Kalhara',
            'phone' => '+94 77 765 1234',
        ]);
    }

    private function promoteUserToSuperAdmin(Role $superAdminRole, string $tenantId, string $email, array $defaults = []): void
    {
        $user = User::withTrashed()->firstOrNew(['email' => $email]);

        $user->tenant_id = $user->tenant_id ?? $tenantId;
        $user->first_name = $user->first_name ?: Arr::get($defaults, 'first_name', 'Super');
        $user->last_name = $user->last_name ?: Arr::get($defaults, 'last_name', 'Admin');
        $user->phone = $user->phone ?: Arr::get($defaults, 'phone');
        $user->password = $user->password ?: Hash::make('Password@123');
        $user->is_active = true;
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->role()->associate($superAdminRole);
        $user->metadata = array_merge($user->metadata ?? [], [
            'granted_role' => 'super_admin',
            'granted_by' => 'database:seed',
        ]);

        if ($user->trashed()) {
            $user->restore();
        }

        $user->save();
    }
}
