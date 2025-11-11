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
use RuntimeException;
use Throwable;

class AuthenticateWithJwt
{
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

        $user = $tokenRecord->user;

        if (! $user || ! $user->is_active) {
            throw new AuthenticationException('User account is inactive.');
        }

        $user->forgetCachedPermissions();

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('auth_token', $tokenRecord);

        if ($tokenRecord->last_used_at?->diffInMinutes(now()) >= 5 || $tokenRecord->last_used_at === null) {
            $tokenRecord->forceFill(['last_used_at' => now()])->save();
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
            throw new AuthenticationException('Token has expired.', previous: $exception);
        } catch (Throwable $exception) {
            throw new AuthenticationException('Token is invalid.', previous: $exception);
        }

        return $decoded;
    }
}
