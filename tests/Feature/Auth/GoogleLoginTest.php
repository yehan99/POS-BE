<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_user_can_login_with_google_id_token(): void
    {
        config(['jwt.secret' => 'test-secret']);

        $user = User::factory()->create([
            'email' => 'test.user@example.com',
            'is_active' => true,
        ]);

        $verifier = Mockery::mock(GoogleIdTokenVerifier::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->with('sample-google-token')
            ->andReturn([
                'email' => 'test.user@example.com',
            ]);

        $this->app->instance(GoogleIdTokenVerifier::class, $verifier);

        $response = $this->postJson('/api/auth/login', [
            'idToken' => 'sample-google-token',
            'deviceName' => 'web-client',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.email', 'test.user@example.com');
        $response->assertJsonStructure([
            'tokenType',
            'accessToken',
            'refreshToken',
            'expiresIn',
            'refreshExpiresIn',
            'user' => [
                'id',
                'firstName',
                'lastName',
                'email',
                'site' => [
                    'id',
                    'name',
                    'slug',
                ],
            ],
        ]);
    }
}
