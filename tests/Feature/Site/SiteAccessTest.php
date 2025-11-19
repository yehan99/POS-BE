<?php

namespace Tests\Feature\Site;

use App\Models\Role;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_admin_only_receives_assigned_site(): void
    {
        config(['jwt.secret' => 'test-secret']);

        $tenant = Tenant::factory()->create();
        $siteA = Site::factory()->for($tenant)->create(['slug' => 'site-a']);
        Site::factory()->for($tenant)->create(['slug' => 'site-b']);

        $role = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin-sites',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'site_id' => $siteA->id,
            'email' => 'admin-sites@example.com',
        ]);

        $tokenBundle = app(AuthTokenService::class)->issue($user, [
            'device_name' => 'test-suite',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenBundle['access_token'])
            ->getJson('/api/sites');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $siteA->id);
    }

    public function test_super_admin_receives_all_sites(): void
    {
        config(['jwt.secret' => 'test-secret']);

        $tenant = Tenant::factory()->create();
        $sites = Site::factory()->count(2)->for($tenant)->create();

        $role = Role::factory()->create([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'site_id' => $sites->first()->id,
            'email' => 'super-sites@example.com',
        ]);

        $tokenBundle = app(AuthTokenService::class)->issue($user, [
            'device_name' => 'test-suite',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenBundle['access_token'])
            ->getJson('/api/sites');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id')->sort()->values();
        $this->assertEquals($sites->pluck('id')->sort()->values()->all(), $ids->all());
    }
}
