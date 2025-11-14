<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class GoogleIdTokenVerifier
{
    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const GOOGLE_ISSUERS = ['https://accounts.google.com', 'accounts.google.com'];

    /**
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function verify(string $idToken): array
    {
        $header = $this->decodeHeader($idToken);
        $kid = Arr::get($header, 'kid');

        if (! $kid) {
            throw ValidationException::withMessages([
                'id_token' => ['The Google token is missing a key identifier.'],
            ]);
        }

        $key = $this->resolveKeyForKid($kid);

        try {
            /** @var array<string, mixed> $payload */
            $payload = (array) JWT::decode($idToken, $key);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'id_token' => ['Failed to verify Google credentials.'],
            ]);
        }

        $this->assertPayloadIsValid($payload);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeHeader(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) < 2) {
            return [];
        }

        $headerJson = JWT::urlsafeB64Decode($segments[0]);

        return json_decode($headerJson, true, flags: JSON_THROW_ON_ERROR);
    }

    private function resolveKeyForKid(string $kid): Key
    {
        $key = $this->fetchKeyFromCache($kid);

        if ($key) {
            return $key;
        }

        // Refresh cache and attempt one more time.
        Cache::forget($this->cacheKey());

        $key = $this->fetchKeyFromCache($kid);

        if (! $key) {
            throw ValidationException::withMessages([
                'id_token' => ['Unable to resolve Google signing keys.'],
            ]);
        }

        return $key;
    }

    private function fetchKeyFromCache(string $kid): ?Key
    {
        $keys = Cache::remember($this->cacheKey(), now()->addHours(6), function () {
            $response = Http::get(self::GOOGLE_CERTS_URL);

            if ($response->failed()) {
                throw new RuntimeException('Failed to download Google public keys.');
            }

            /** @var array{keys: array<int, array<string, mixed>>} $remoteKeys */
            $remoteKeys = $response->json();

            return $remoteKeys['keys'] ?? [];
        });

        $keySet = JWK::parseKeySet(['keys' => $keys], 'RS256');

        return $keySet[$kid] ?? null;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws ValidationException
     */
    private function assertPayloadIsValid(array $payload): void
    {
        $clientId = config('services.google.client_id');

        if (! $clientId) {
            throw new RuntimeException('Google client ID is not configured.');
        }

        $issuer = Arr::get($payload, 'iss');

        if (! $issuer || ! in_array($issuer, self::GOOGLE_ISSUERS, true)) {
            throw ValidationException::withMessages([
                'id_token' => ['Google token issuer is invalid.'],
            ]);
        }

        $audience = Arr::get($payload, 'aud');

        if (! $audience || ! Str::of($audience)->exactly($clientId)) {
            throw ValidationException::withMessages([
                'id_token' => ['Google token audience is not recognized.'],
            ]);
        }

        if (Arr::get($payload, 'email_verified') !== true) {
            throw ValidationException::withMessages([
                'id_token' => ['Google account email is not verified.'],
            ]);
        }

        $expiration = Arr::get($payload, 'exp');

        if (! $expiration || now()->timestamp >= (int) $expiration) {
            throw ValidationException::withMessages([
                'id_token' => ['Google token has expired.'],
            ]);
        }
    }

    private function cacheKey(): string
    {
        return 'google.oauth.certs';
    }
}
