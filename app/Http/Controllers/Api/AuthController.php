<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Resources\UserResource;
use App\Models\AuthToken;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthTokenService $tokens,
        private readonly GoogleIdTokenVerifier $googleVerifier,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $googlePayload = $this->googleVerifier->verify($data['id_token']);

        $email = strtolower($googlePayload['email'] ?? '');

        if (! $email) {
            throw ValidationException::withMessages([
                'id_token' => ['The Google token does not include an email address.'],
            ]);
        }

        $user = User::query()
            ->with('role.permissions', 'permissions', 'tenant', 'site')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'id_token' => ['This account is not provisioned in the system.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'id_token' => ['Account is currently inactive.'],
            ]);
        }

        $updates = ['last_login_at' => now()];

        if (! $user->email_verified_at) {
            $updates['email_verified_at'] = now();
        }

        $user->forceFill($updates)->save();

        $tokenBundle = $this->tokens->issue($user, [
            'device_name' => $data['device_name'] ?? 'web',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->tokenResponse($user, $tokenBundle);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->tokens->refresh(
            $request->validated()['refresh_token'],
            [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        return $this->tokenResponse($tokens['user'], $tokens, status: 200, includeUser: false);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated');
        }

        $user->loadMissing('role.permissions', 'permissions', 'tenant');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var AuthToken|null $currentToken */
        $currentToken = $request->attributes->get('auth_token');

        if (! $currentToken) {
            throw new AuthenticationException('Token context missing.');
        }

        $payload = $request->validate([
            'all_devices' => ['sometimes', 'boolean'],
        ]);

        $this->tokens->revoke($currentToken, (bool) ($payload['all_devices'] ?? false));

        return response()->json([
            'revoked' => true,
        ]);
    }

    private function tokenResponse(User $user, array $bundle, int $status = 200, bool $includeUser = true): JsonResponse
    {
        $response = [
            'tokenType' => 'Bearer',
            'accessToken' => $bundle['access_token'],
            'refreshToken' => $bundle['refresh_token'],
            'expiresIn' => $bundle['expires_in'],
            'refreshExpiresIn' => $bundle['refresh_expires_in'],
        ];

        if ($includeUser) {
            $user->loadMissing('role.permissions', 'permissions', 'tenant', 'site');
            $response['user'] = new UserResource($user);
        }

        return response()->json($response, $status);
    }
}
