<?php

namespace Tests\Feature\User;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_new_user(): void
    {
        config(['jwt.secret' => 'test-secret']);

        $tenant = Tenant::factory()->create();
        $site = Site::factory()->for($tenant)->create();

        $permission = Permission::query()->create([
            'name' => 'Manage Users',
            'slug' => 'user.management',
            'module' => 'settings',
        ]);

        $role = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-'.fake()->unique()->numberBetween(1000, 9999),
        ]);
        $role->permissions()->sync([$permission->id]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'site_id' => $site->id,
            'email' => 'admin@example.com',
        ]);

        $tokenBundle = app(AuthTokenService::class)->issue($admin, [
            'device_name' => 'test-suite',
        ]);

        $newSite = Site::factory()->for($tenant)->create([
            'name' => 'Test Branch',
            'slug' => 'test-branch',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenBundle['access_token'])
            ->postJson('/api/users', [
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'email' => 'jane.doe@example.com',
                'roleId' => $role->id,
                'siteId' => $newSite->id,
            ]);

        $response->assertCreated();
    $response->assertJsonPath('email', 'jane.doe@example.com');
    $response->assertJsonPath('role.id', $role->id);
    $response->assertJsonPath('site.id', $newSite->id);

        $this->assertDatabaseHas('users', [
            'email' => 'jane.doe@example.com',
            'site_id' => $newSite->id,
            'tenant_id' => $tenant->id,
        ]);
    }
}
