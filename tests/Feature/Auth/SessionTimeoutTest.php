<?php

namespace Tests\Feature\Auth;

use App\Models\AuthToken;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => 'test-secret',
            'jwt.idle_timeout' => 1_800,
            'jwt.idle_warning' => 60,
        ]);
    }

    public function test_keep_alive_updates_last_used_timestamp(): void
    {
        $user = User::factory()->create();

        /** @var AuthTokenService $tokens */
        $tokens = app(AuthTokenService::class);
        $bundle = $tokens->issue($user, ['device_name' => 'test-suite']);

        $record = AuthToken::query()
            ->where('access_token_id', $bundle['token_id'])
            ->firstOrFail();

        $originalTimestamp = $record->last_used_at;

        $response = $this->withHeader('Authorization', 'Bearer '.$bundle['access_token'])
            ->postJson('/api/auth/keep-alive');

        $response->assertOk()
            ->assertJsonFragment([
                'ok' => true,
                'idleTimeoutSeconds' => 1_800,
                'warningSeconds' => 60,
            ]);

        $record->refresh();

        $this->assertTrue(
            $record->last_used_at->greaterThanOrEqualTo($originalTimestamp)
        );
    }

    public function test_requests_after_idle_timeout_are_rejected(): void
    {
        $user = User::factory()->create();

        /** @var AuthTokenService $tokens */
        $tokens = app(AuthTokenService::class);
        $bundle = $tokens->issue($user, ['device_name' => 'test-suite']);

        $record = AuthToken::query()
            ->where('access_token_id', $bundle['token_id'])
            ->firstOrFail();

        $record->forceFill([
            'last_used_at' => now()->subSeconds(config('jwt.idle_timeout') + 5),
        ])->save();

        $response = $this->withHeader('Authorization', 'Bearer '.$bundle['access_token'])
            ->getJson('/api/profile');

        $response->assertStatus(401);
        $response->assertJsonFragment([
            'message' => 'Session expired due to inactivity.',
        ]);
    }
}
