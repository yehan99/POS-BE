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

        $permission = $this->seedUserManagementPermission();

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

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenBundle['access_token'])
            ->postJson('/api/users', [
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'email' => 'jane.doe@example.com',
                'roleId' => $role->id,
                'siteId' => $site->id,
            ]);

        $response->assertCreated();
    $response->assertJsonPath('email', 'jane.doe@example.com');
    $response->assertJsonPath('role.id', $role->id);
    $response->assertJsonPath('site.id', $site->id);

        $this->assertDatabaseHas('users', [
            'email' => 'jane.doe@example.com',
            'site_id' => $site->id,
            'tenant_id' => $tenant->id,
        ]);
    }

        public function test_regular_admin_cannot_assign_user_to_another_site(): void
        {
            config(['jwt.secret' => 'test-secret']);

            $tenant = Tenant::factory()->create();
            $primarySite = Site::factory()->for($tenant)->create();
            $secondarySite = Site::factory()->for($tenant)->create();

            $permission = $this->seedUserManagementPermission();

            $role = Role::factory()->create([
                'name' => 'Admin',
                'slug' => 'admin-'.fake()->unique()->numberBetween(2000, 9999),
            ]);
            $role->permissions()->sync([$permission->id]);

            $admin = User::factory()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $role->id,
                'site_id' => $primarySite->id,
                'email' => 'admin@example.com',
            ]);

            $tokenBundle = app(AuthTokenService::class)->issue($admin, [
                'device_name' => 'test-suite',
            ]);

            $response = $this->withHeader('Authorization', 'Bearer '.$tokenBundle['access_token'])
                ->postJson('/api/users', [
                    'firstName' => 'Branch',
                    'lastName' => 'Manager',
                    'email' => 'branch.manager@example.com',
                    'roleId' => $role->id,
                    'siteId' => $secondarySite->id,
                ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['siteId']);

            $this->assertDatabaseMissing('users', [
                'email' => 'branch.manager@example.com',
            ]);
        }

        public function test_regular_admin_listing_is_restricted_to_assigned_site(): void
        {
            config(['jwt.secret' => 'test-secret']);

            $tenant = Tenant::factory()->create();
            $siteA = Site::factory()->for($tenant)->create(['slug' => 'site-a']);
            $siteB = Site::factory()->for($tenant)->create(['slug' => 'site-b']);

            $permission = $this->seedUserManagementPermission();

            $role = Role::factory()->create([
                'name' => 'Admin',
                'slug' => 'admin-'.fake()->unique()->numberBetween(3000, 9999),
            ]);
            $role->permissions()->sync([$permission->id]);

            $admin = User::factory()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $role->id,
                'site_id' => $siteA->id,
                'email' => 'admin@example.com',
            ]);

            $tokenBundle = app(AuthTokenService::class)->issue($admin, [
                'device_name' => 'test-suite',
            ]);

            $userInSiteA = User::factory()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $role->id,
                'site_id' => $siteA->id,
                'email' => 'site-a@example.com',
            ]);

            $userInSiteB = User::factory()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $role->id,
                'site_id' => $siteB->id,
                'email' => 'site-b@example.com',
            ]);

            $response = $this->withHeader('Authorization', 'Bearer '.$tokenBundle['access_token'])
                ->getJson('/api/users');

            $response->assertOk();
            $emails = collect($response->json('data'))->pluck('email');

            $this->assertTrue($emails->contains($userInSiteA->email));
            $this->assertFalse($emails->contains($userInSiteB->email));

            $response = $this->withHeader('Authorization', 'Bearer '.$tokenBundle['access_token'])
                ->getJson('/api/users?siteId='.$siteB->id);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['siteId']);
        }

        public function test_super_admin_can_filter_users_by_site(): void
        {
            config(['jwt.secret' => 'test-secret']);

            $tenant = Tenant::factory()->create();
            $siteA = Site::factory()->for($tenant)->create(['slug' => 'site-a']);
            $siteB = Site::factory()->for($tenant)->create(['slug' => 'site-b']);

            $permission = $this->seedUserManagementPermission();

            $superRole = Role::factory()->create([
                'name' => 'Super Admin',
                'slug' => 'super_admin',
            ]);
            $superRole->permissions()->sync([$permission->id]);

            $superAdmin = User::factory()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $superRole->id,
                'site_id' => $siteA->id,
                'email' => 'super@example.com',
            ]);

            $tokenBundle = app(AuthTokenService::class)->issue($superAdmin, [
                'device_name' => 'test-suite',
            ]);

            User::factory()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $superRole->id,
                'site_id' => $siteA->id,
                'email' => 'site-a@example.com',
            ]);

            $userInSiteB = User::factory()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $superRole->id,
                'site_id' => $siteB->id,
                'email' => 'site-b@example.com',
            ]);

            $response = $this->withHeader('Authorization', 'Bearer '.$tokenBundle['access_token'])
                ->getJson('/api/users?siteId='.$siteB->id);

            $response->assertOk();
            $emails = collect($response->json('data'))->pluck('email');
            $this->assertTrue($emails->contains($userInSiteB->email));
            $this->assertCount(1, $emails);
        }

        private function seedUserManagementPermission(): Permission
        {
            return Permission::query()->firstOrCreate(
                ['slug' => 'user.management'],
                [
                    'name' => 'Manage Users',
                    'module' => 'settings',
                ]
            );
        }
}
