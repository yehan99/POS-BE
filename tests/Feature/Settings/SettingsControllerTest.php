<?php

namespace Tests\Feature\Settings;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_fetch_settings(): void
    {
        config(['jwt.secret' => 'test-secret']);

        [$user, $token, $tenant, $site] = $this->provisionUserWithSettingsAccess();

        $tenant->forceFill([
            'settings' => [
                'general' => [
                    'businessName' => 'Paradise Retail',
                    'businessEmail' => 'ops@paradisepos.com',
                    'businessPhone' => '+94 11 200 3000',
                    'timezone' => 'Asia/Kolkata',
                    'currency' => 'INR',
                    'locale' => 'en-IN',
                    'invoicePrefix' => 'PRD',
                    'invoiceStartNumber' => 5000,
                    'defaultSiteId' => null,
                ],
                'notifications' => [
                    'sendDailySummary' => false,
                    'lowStockAlerts' => true,
                    'newOrderAlerts' => false,
                    'digestFrequency' => 'weekly',
                    'escalationEmail' => 'alerts@paradisepos.com',
                ],
                'updatedAt' => now()->subDay()->toIso8601String(),
            ],
        ])->save();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/settings');

        $response->assertOk();
        $response->assertJsonPath('general.businessName', 'Paradise Retail');
        $response->assertJsonPath('notifications.digestFrequency', 'weekly');
    }

    public function test_authorized_user_can_update_general_settings(): void
    {
        config(['jwt.secret' => 'test-secret']);

        [$user, $token, $tenant, $site] = $this->provisionUserWithSettingsAccess(includeUpdate: true, includeRead: true);

        $payload = [
            'businessName' => 'Coastal Retail Group',
            'businessEmail' => 'hello@coastal.lk',
            'businessPhone' => '+94 11 111 2222',
            'timezone' => 'Asia/Singapore',
            'currency' => 'inr',
            'locale' => 'en-SG',
            'invoicePrefix' => 'cr',
            'invoiceStartNumber' => 2750,
            'defaultSiteId' => $site->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/settings/general', $payload);

        $response->assertOk();
        $response->assertJsonPath('general.businessName', 'Coastal Retail Group');
        $response->assertJsonPath('general.currency', 'INR');
        $response->assertJsonPath('general.invoicePrefix', 'CR');
        $response->assertJsonPath('general.invoiceStartNumber', 2750);

        $tenant->refresh();
        $this->assertEquals('Coastal Retail Group', data_get($tenant->settings, 'general.businessName'));
        $this->assertEquals('INR', data_get($tenant->settings, 'general.currency'));
    }

    public function test_authorized_user_can_update_notification_settings(): void
    {
        config(['jwt.secret' => 'test-secret']);

        [$user, $token, $tenant, $site] = $this->provisionUserWithSettingsAccess(includeUpdate: true, includeRead: true);

        $payload = [
            'sendDailySummary' => false,
            'lowStockAlerts' => false,
            'newOrderAlerts' => true,
            'digestFrequency' => 'monthly',
            'escalationEmail' => 'director@paradisepos.com',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/settings/notifications', $payload);

        $response->assertOk();
        $response->assertJsonPath('notifications.newOrderAlerts', true);
        $response->assertJsonPath('notifications.digestFrequency', 'monthly');

        $tenant->refresh();
        $this->assertTrue((bool) data_get($tenant->settings, 'notifications.newOrderAlerts'));
        $this->assertEquals('monthly', data_get($tenant->settings, 'notifications.digestFrequency'));
    }

    private function provisionUserWithSettingsAccess(bool $includeUpdate = true, bool $includeRead = true): array
    {
        $tenant = Tenant::factory()->create();
        $site = Site::factory()->for($tenant)->create();
        $role = Role::factory()->create([
            'slug' => 'settings-'.Str::random(6),
        ]);

        $permissions = $this->seedSettingsPermissions();
        $permissionIds = [];

        if ($includeRead) {
            $permissionIds[] = $permissions['settings.read']->id;
        }

        if ($includeUpdate) {
            $permissionIds[] = $permissions['settings.update']->id;
        }

        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'role_id' => $role->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        $tokenBundle = app(AuthTokenService::class)->issue($user, [
            'device_name' => 'test-suite',
        ]);

        return [$user, $tokenBundle['access_token'], $tenant, $site];
    }

    private function seedSettingsPermissions(): array
    {
        $read = Permission::query()->firstOrCreate(
            ['slug' => 'settings.read'],
            [
                'name' => 'View Settings',
                'module' => 'settings',
            ]
        );

        $update = Permission::query()->firstOrCreate(
            ['slug' => 'settings.update'],
            [
                'name' => 'Update Settings',
                'module' => 'settings',
            ]
        );

        return [
            'settings.read' => $read,
            'settings.update' => $update,
        ];
    }
}
