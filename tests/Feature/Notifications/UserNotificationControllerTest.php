<?php

namespace Tests\Feature\Notifications;

use App\Models\Role;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_notifications(): void
    {
        config(['jwt.secret' => 'test-secret']);

        [$user, $token] = $this->provisionUser();

        UserNotification::factory()->for($user, 'user')->for($user->tenant)->count(3)->create();
        UserNotification::factory()->count(2)->create(); // foreign notifications

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/notifications');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_can_fetch_unread_count(): void
    {
        config(['jwt.secret' => 'test-secret']);

        [$user, $token] = $this->provisionUser();

        UserNotification::factory()->for($user, 'user')->for($user->tenant)->count(2)->create([
            'is_read' => false,
        ]);
        UserNotification::factory()->for($user, 'user')->for($user->tenant)->state([
            'is_read' => true,
        ])->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/notifications/unread-count');

        $response->assertOk();
        $response->assertJsonPath('unread', 2);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        config(['jwt.secret' => 'test-secret']);

        [$user, $token] = $this->provisionUser();

        $notification = UserNotification::factory()
            ->for($user, 'user')
            ->for($user->tenant)
            ->create(['is_read' => false]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertOk();
        $response->assertJsonPath('notification.isRead', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        config(['jwt.secret' => 'test-secret']);

        [$user, $token] = $this->provisionUser();

        UserNotification::factory()->for($user, 'user')->for($user->tenant)->count(3)->create([
            'is_read' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/notifications/mark-all-read');

        $response->assertOk();

        $this->assertEquals(0, UserNotification::query()->where('user_id', $user->id)->where('is_read', false)->count());
    }

    private function provisionUser(): array
    {
        $tenant = Tenant::factory()->create();
        $site = Site::factory()->for($tenant)->create();
        $role = Role::factory()->create();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'role_id' => $role->id,
        ]);

        $tokenBundle = app(AuthTokenService::class)->issue($user, [
            'device_name' => 'test-suite',
        ]);

        return [$user, $tokenBundle['access_token']];
    }
}
