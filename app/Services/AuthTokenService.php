<?php

namespace App\Services;

use App\Models\AuthToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthTokenService
{
    /**
     * Issue a new token pair for the given user.
     *
     * @param array{device_name?: ?string, ip_address?: ?string, user_agent?: ?string} $context
     * @return array{
     *     access_token: string,
     *     refresh_token: string,
     *     expires_in: int,
     *     refresh_expires_in: int,
    *     access_token_expires_at: \Illuminate\Support\Carbon,
    *     refresh_token_expires_at: \Illuminate\Support\Carbon,
     *     token_id: string,
     *     user: User
     * }
     */
    public function issue(User $user, array $context = []): array
    {
        $user->loadMissing('role.permissions', 'permissions', 'tenant');
        $user->forgetCachedPermissions();

        $now = now();
        $accessExpiresAt = $now->copy()->addSeconds(config('jwt.access_ttl', 900));
        $refreshExpiresAt = $now->copy()->addSeconds(config('jwt.refresh_ttl', 1_209_600));

        $tokenId = (string) Str::ulid();

        $payload = [
            'iss' => config('jwt.issuer'),
            'sub' => $user->getKey(),
            'tid' => $user->tenant_id,
            'rid' => $user->role_id,
            'perms' => $user->permissionSlugs()->values()->all(),
            'iat' => $now->timestamp,
            'exp' => $accessExpiresAt->timestamp,
            'jti' => $tokenId,
        ];

        $secret = config('jwt.secret');
        $algo = config('jwt.algo', 'HS256');

        if (! $secret) {
            throw new RuntimeException('JWT secret is not configured.');
        }

        $accessToken = JWT::encode($payload, $secret, $algo);
        $refreshToken = Str::random(64);
        $refreshTokenHash = $this->hashRefreshToken($refreshToken);

        $record = AuthToken::query()->create([
            'user_id' => $user->getKey(),
            'name' => Arr::get($context, 'device_name'),
            'access_token_id' => $tokenId,
            'refresh_token_hash' => $refreshTokenHash,
            'access_token_expires_at' => $accessExpiresAt,
            'refresh_token_expires_at' => $refreshExpiresAt,
            'ip_address' => Arr::get($context, 'ip_address'),
            'user_agent' => Arr::get($context, 'user_agent'),
            'revoked' => false,
            'last_used_at' => $now,
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $accessExpiresAt->diffInSeconds($now),
            'refresh_expires_in' => $refreshExpiresAt->diffInSeconds($now),
            'access_token_expires_at' => $accessExpiresAt,
            'refresh_token_expires_at' => $refreshExpiresAt,
            'token_id' => $record->access_token_id,
            'user' => $user,
        ];
    }

    /**
     * Rotate the token pair using an existing refresh token.
     *
     * @param array{device_name?: ?string, ip_address?: ?string, user_agent?: ?string} $context
     * @return array{
     *     access_token: string,
     *     refresh_token: string,
     *     expires_in: int,
     *     refresh_expires_in: int,
    *     access_token_expires_at: \Illuminate\Support\Carbon,
    *     refresh_token_expires_at: \Illuminate\Support\Carbon,
     *     token_id: string,
     *     user: User
     * }
     */
    public function refresh(string $refreshToken, array $context = []): array
    {
        $hash = $this->hashRefreshToken($refreshToken);

        return DB::transaction(function () use ($hash, $context) {
            $tokenRecord = AuthToken::query()
                ->with('user.role.permissions', 'user.permissions', 'user.tenant')
                ->lockForUpdate()
                ->where('refresh_token_hash', $hash)
                ->where('revoked', false)
                ->first();

            if (! $tokenRecord) {
                throw ValidationException::withMessages([
                    'refresh_token' => ['The refresh token is invalid.'],
                ]);
            }

            if ($tokenRecord->refresh_token_expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'refresh_token' => ['The refresh token has expired.'],
                ]);
            }

            $user = $tokenRecord->user;

            if (! $user || ! $user->is_active) {
                throw ValidationException::withMessages([
                    'refresh_token' => ['The account is inactive.'],
                ]);
            }

            $user->forgetCachedPermissions();

            $now = now();
            $accessExpiresAt = $now->copy()->addSeconds(config('jwt.access_ttl', 900));
            $refreshExpiresAt = $now->copy()->addSeconds(config('jwt.refresh_ttl', 1_209_600));
            $tokenId = (string) Str::ulid();

            $payload = [
                'iss' => config('jwt.issuer'),
                'sub' => $user->getKey(),
                'tid' => $user->tenant_id,
                'rid' => $user->role_id,
                'perms' => $user->permissionSlugs()->values()->all(),
                'iat' => $now->timestamp,
                'exp' => $accessExpiresAt->timestamp,
                'jti' => $tokenId,
            ];

            $secret = config('jwt.secret');
            $algo = config('jwt.algo', 'HS256');

            if (! $secret) {
                throw new RuntimeException('JWT secret is not configured.');
            }

            $accessToken = JWT::encode($payload, $secret, $algo);
            $newRefreshToken = Str::random(64);
            $tokenRecord->forceFill([
                'access_token_id' => $tokenId,
                'access_token_expires_at' => $accessExpiresAt,
                'refresh_token_expires_at' => $refreshExpiresAt,
                'refresh_token_hash' => $this->hashRefreshToken($newRefreshToken),
                'ip_address' => Arr::get($context, 'ip_address', $tokenRecord->ip_address),
                'user_agent' => Arr::get($context, 'user_agent', $tokenRecord->user_agent),
                'last_used_at' => $now,
            ])->save();

            return [
                'access_token' => $accessToken,
                'refresh_token' => $newRefreshToken,
                'expires_in' => $accessExpiresAt->diffInSeconds($now),
                'refresh_expires_in' => $refreshExpiresAt->diffInSeconds($now),
                'access_token_expires_at' => $accessExpiresAt,
                'refresh_token_expires_at' => $refreshExpiresAt,
                'token_id' => $tokenRecord->access_token_id,
                'user' => $user,
            ];
        });
    }

    public function revoke(AuthToken $token, bool $allDevices = false): void
    {
        if ($allDevices) {
            AuthToken::query()
                ->where('user_id', $token->user_id)
                ->update([
                    'revoked' => true,
                    'access_token_expires_at' => now(),
                    'refresh_token_expires_at' => now(),
                ]);

            return;
        }

        $token->forceFill([
            'revoked' => true,
            'access_token_expires_at' => now(),
            'refresh_token_expires_at' => now(),
        ])->save();
    }

    private function hashRefreshToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
