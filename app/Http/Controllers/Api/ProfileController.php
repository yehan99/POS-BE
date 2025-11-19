<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401, 'Unauthenticated.');

        $user->loadMissing('role.permissions', 'permissions', 'tenant', 'site');

        return response()->json([
            'user' => new UserResource($user),
            'profile' => $this->buildProfilePayload($user->metadata ?? []),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401, 'Unauthenticated.');

        $data = $request->validated();

        $user->fill(Arr::only($data, ['first_name', 'last_name', 'phone']));

        $user->metadata = $this->mergeProfileMetadata($user->metadata ?? [], $data);

        $user->save();

        $user->loadMissing('role.permissions', 'permissions', 'tenant', 'site');

        return response()->json([
            'user' => new UserResource($user),
            'profile' => $this->buildProfilePayload($user->metadata ?? []),
        ]);
    }

    private function buildProfilePayload(array $metadata): array
    {
        return [
            'avatarUrl' => Arr::get($metadata, 'profile.avatarUrl'),
            'jobTitle' => Arr::get($metadata, 'profile.jobTitle'),
            'department' => Arr::get($metadata, 'profile.department'),
            'bio' => Arr::get($metadata, 'profile.bio'),
            'preferences' => $this->resolvePreferences($metadata),
        ];
    }

    private function mergeProfileMetadata(array $metadata, array $data): array
    {
        $profile = Arr::get($metadata, 'profile', []);

        foreach ([
            'avatar_url' => 'avatarUrl',
            'job_title' => 'jobTitle',
            'department' => 'department',
            'bio' => 'bio',
        ] as $inputKey => $profileKey) {
            if (array_key_exists($inputKey, $data)) {
                $profile[$profileKey] = $data[$inputKey];
            }
        }

        if (array_key_exists('preferences', $data)) {
            $profile['preferences'] = $this->mergePreferences(
                $profile['preferences'] ?? null,
                $data['preferences'] ?? []
            );
        } elseif (! isset($profile['preferences'])) {
            $profile['preferences'] = $this->defaultPreferences();
        }

        $metadata['profile'] = $profile;

        return $metadata;
    }

    private function resolvePreferences(array $metadata): array
    {
        $existing = Arr::get($metadata, 'profile.preferences');

        if (! is_array($existing)) {
            $existing = [];
        }

        return $this->mergePreferences(null, $existing);
    }

    private function mergePreferences(?array $original, array $incoming): array
    {
        $base = $original ?? $this->defaultPreferences();

        $merged = array_replace_recursive($base, $incoming);

        return array_replace_recursive($this->defaultPreferences(), $merged);
    }

    private function defaultPreferences(): array
    {
        return [
            'timezone' => config('app.timezone', 'UTC'),
            'language' => config('app.locale', 'en'),
            'theme' => 'system',
            'digestEmails' => true,
            'notifications' => [
                'newOrders' => true,
                'lowStock' => true,
                'productUpdates' => true,
            ],
        ];
    }
}
