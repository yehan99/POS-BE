<?php

namespace App\Http\Middleware;

use App\Models\AuthToken;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class AuthenticateWithJwt
{
    private const LAST_USED_UPDATE_INTERVAL = 60; // seconds

    public function handle(Request $request, Closure $next)
    {
        $rawToken = $this->extractTokenFromRequest($request);

        if (! $rawToken) {
            throw new AuthenticationException('Missing access token.');
        }

        $payload = $this->decodeToken($rawToken);

        $tokenRecord = AuthToken::query()
            ->with('user.role.permissions', 'user.permissions', 'user.tenant')
            ->where('access_token_id', $payload['jti'] ?? null)
            ->where('revoked', false)
            ->first();

        if (! $tokenRecord || $tokenRecord->access_token_expires_at->isPast()) {
            throw new AuthenticationException('Token has expired.');
        }

        $now = now();
        $idleTimeout = (int) config('jwt.idle_timeout', 0);

        if ($idleTimeout > 0 && $tokenRecord->last_used_at && $tokenRecord->last_used_at->diffInSeconds($now) >= $idleTimeout) {
            $this->expireToken($tokenRecord, $now);

            throw new AuthenticationException('Session expired due to inactivity.');
        }

        $user = $tokenRecord->user;

        if (! $user || ! $user->is_active) {
            throw new AuthenticationException('User account is inactive.');
        }

        $user->forgetCachedPermissions();

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('auth_token', $tokenRecord);

        if (
            $tokenRecord->last_used_at === null
            || $tokenRecord->last_used_at->diffInSeconds($now) >= self::LAST_USED_UPDATE_INTERVAL
        ) {
            $tokenRecord->forceFill(['last_used_at' => $now])->save();
        }

        return $next($request);
    }

    private function extractTokenFromRequest(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return $request->query('access_token');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeToken(string $token): array
    {
        $secret = config('jwt.secret');
        $algo = config('jwt.algo', 'HS256');

        if (! $secret) {
            throw new RuntimeException('JWT secret is not configured.');
        }

        try {
            $decoded = (array) JWT::decode($token, new Key($secret, $algo));
        } catch (ExpiredException $exception) {
            throw new AuthenticationException('Token has expired.');
        } catch (Throwable $exception) {
            throw new AuthenticationException('Token is invalid.');
        }

        return $decoded;
    }

    private function expireToken(AuthToken $token, Carbon $timestamp): void
    {
        $token->forceFill([
            'revoked' => true,
            'access_token_expires_at' => $timestamp,
            'refresh_token_expires_at' => $timestamp,
        ])->save();
    }
}
