<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\AuthToken;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuthTokenService $tokens)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $tenantData = Arr::get($data, 'tenant', []);

            $tenant = Tenant::query()->create([
                'name' => Arr::get($tenantData, 'name'),
                'business_type' => Arr::get($tenantData, 'business_type', 'retail'),
                'country' => Arr::get($tenantData, 'country', 'LK'),
                'phone' => Arr::get($tenantData, 'phone'),
                'settings' => Arr::get($tenantData, 'settings', [
                    'currency' => 'LKR',
                    'timezone' => 'Asia/Colombo',
                    'language' => 'en',
                ]),
                'is_active' => true,
            ]);

            $roleSlug = Arr::get($data, 'role_slug', 'admin');

            $role = Role::query()->where('slug', $roleSlug)->first();

            if (! $role) {
                throw ValidationException::withMessages([
                    'role_slug' => ["Role '{$roleSlug}' is not available."],
                ]);
            }

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'role_id' => $role->id,
                'first_name' => Arr::get($data, 'first_name'),
                'last_name' => Arr::get($data, 'last_name'),
                'email' => Arr::get($data, 'email'),
                'phone' => Arr::get($data, 'phone'),
                'password' => Hash::make(Arr::get($data, 'password')),
                'is_active' => true,
                'metadata' => [
                    'registered_via' => 'api',
                ],
            ]);

            return $user;
        });

        $user->loadMissing('role.permissions', 'permissions', 'tenant');

        $tokenBundle = $this->tokens->issue($user, [
            'device_name' => $data['device_name'] ?? 'web',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->tokenResponse($user, $tokenBundle, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()
            ->with('role.permissions', 'permissions', 'tenant')
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Account is currently inactive.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

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
            $user->loadMissing('role.permissions', 'permissions', 'tenant');
            $response['user'] = new UserResource($user);
        }

        return response()->json($response, $status);
    }
}
