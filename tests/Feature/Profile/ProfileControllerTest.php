<?php

namespace Tests\Feature\Profile;

use App\Models\Role;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile(): void
    {
        config(['jwt.secret' => 'test-secret']);

        [$user, $token] = $this->provisionUser();

        $user->forceFill([
            'metadata' => [
                'profile' => [
                    'avatarUrl' => 'https://cdn.paradisepos.com/avatar.png',
                    'jobTitle' => 'Inventory Lead',
                    'department' => 'Operations',
                    'preferences' => [
                        'timezone' => 'Asia/Colombo',
                        'language' => 'si',
                        'theme' => 'dark',
                        'notifications' => [
                            'newOrders' => true,
                            'lowStock' => false,
                            'productUpdates' => true,
                        ],
                    ],
                ],
            ],
        ])->save();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/profile');

        $response->assertOk();
        $response->assertJsonPath('user.email', $user->email);
        $response->assertJsonPath('profile.avatarUrl', 'https://cdn.paradisepos.com/avatar.png');
        $response->assertJsonPath('profile.preferences.timezone', 'Asia/Colombo');
        $response->assertJsonPath('profile.preferences.notifications.lowStock', false);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        config(['jwt.secret' => 'test-secret']);

        [$user, $token, $tenant, $site, $role] = $this->provisionUser(includeMeta: false);

        $payload = [
            'firstName' => 'Maya',
            'lastName' => 'Perera',
            'phone' => '+94 71 555 1122',
            'jobTitle' => 'Retail Manager',
            'preferences' => [
                'timezone' => 'Asia/Singapore',
                'theme' => 'light',
                'notifications' => [
                    'lowStock' => false,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/profile', $payload);

        $response->assertOk();
        $response->assertJsonPath('user.firstName', 'Maya');
        $response->assertJsonPath('profile.jobTitle', 'Retail Manager');
        $response->assertJsonPath('profile.preferences.timezone', 'Asia/Singapore');
        $response->assertJsonPath('profile.preferences.notifications.lowStock', false);

        $user->refresh();
        $this->assertSame('Maya', $user->first_name);
        $this->assertSame($site->id, $user->site_id, 'Site should remain unchanged');
        $this->assertSame($role->id, $user->role_id, 'Role should remain unchanged');
        $this->assertEquals('Retail Manager', data_get($user->metadata, 'profile.jobTitle'));
        $this->assertEquals('Asia/Singapore', data_get($user->metadata, 'profile.preferences.timezone'));
    }

    public function test_updating_site_or_role_is_rejected(): void
    {
        config(['jwt.secret' => 'test-secret']);

        [$user, $token, $tenant, $site, $role] = $this->provisionUser(includeMeta: false);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/profile', [
                'siteId' => Site::factory()->create()->id,
                'roleId' => Role::factory()->create()->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site_id', 'role_id']);
    }

    private function provisionUser(bool $includeMeta = true): array
    {
        $tenant = Tenant::factory()->create();
        $site = Site::factory()->for($tenant)->create();
        $role = Role::factory()->create();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'role_id' => $role->id,
            'metadata' => $includeMeta ? [
                'profile' => [
                    'preferences' => [
                        'timezone' => 'Asia/Colombo',
                    ],
                ],
            ] : null,
        ]);

        $tokenBundle = app(AuthTokenService::class)->issue($user, [
            'device_name' => 'test-suite',
        ]);

        return [$user, $tokenBundle['access_token'], $tenant, $site, $role];
    }
}
