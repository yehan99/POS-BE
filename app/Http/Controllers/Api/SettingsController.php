<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Settings\UpdateNotificationSettingsRequest;
use App\Models\Tenant;
use App\Support\Concerns\HandlesSiteAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    use HandlesSiteAccess;

    public function show(Request $request): JsonResponse
    {
        $this->ensureCanReadSettings($request);

        $tenant = $this->resolveTenant($request);

        return response()->json($this->formatState($tenant));
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $payload = $request->validated();

        $payload['invoiceStartNumber'] = (int) ($payload['invoiceStartNumber'] ?? 1000);
        $payload['taxRate'] = (float) ($payload['taxRate'] ?? 0);

        if (! empty($payload['defaultSiteId'])) {
            $this->assertSiteAccessible($request->user(), (string) $payload['defaultSiteId']);
        }

        $state = $this->persistSettings($tenant, [
            'general' => array_merge($this->defaultGeneral($tenant), $payload),
        ]);

        return response()->json($state);
    }

    public function updateNotifications(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $payload = $request->validated();

        $payload['sendDailySummary'] = (bool) ($payload['sendDailySummary'] ?? false);
        $payload['lowStockAlerts'] = (bool) ($payload['lowStockAlerts'] ?? false);
        $payload['newOrderAlerts'] = (bool) ($payload['newOrderAlerts'] ?? false);
        $payload['digestFrequency'] = strtolower($payload['digestFrequency'] ?? 'daily');
        $payload['escalationEmail'] = $payload['escalationEmail'] ?? null;

        $state = $this->persistSettings($tenant, [
            'notifications' => array_merge($this->defaultNotifications(), $payload),
        ]);

        return response()->json($state);
    }

    private function ensureCanReadSettings(Request $request): void
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission('settings.read')) {
            throw new AuthorizationException('You do not have permission to view settings.');
        }
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;

        if (! $tenant) {
            abort(404, 'Tenant context is unavailable.');
        }

        return $tenant;
    }

    private function persistSettings(Tenant $tenant, array $changes): array
    {
        $existing = is_array($tenant->settings) ? $tenant->settings : [];

        $merged = array_replace_recursive($existing, $changes);
        $merged['updatedAt'] = now()->toIso8601String();

        $tenant->forceFill(['settings' => $merged])->save();
        $tenant->refresh();

        return $this->formatState($tenant);
    }

    private function formatState(Tenant $tenant): array
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];

        $general = array_merge(
            $this->defaultGeneral($tenant),
            Arr::get($settings, 'general', [])
        );
        $general['invoiceStartNumber'] = (int) ($general['invoiceStartNumber'] ?? 1000);
        $general['defaultSiteId'] = $general['defaultSiteId'] ?? null;
        $general['taxRate'] = (float) ($general['taxRate'] ?? 0);

        $notifications = array_merge(
            $this->defaultNotifications(),
            Arr::get($settings, 'notifications', [])
        );
        $notifications['sendDailySummary'] = (bool) ($notifications['sendDailySummary'] ?? false);
        $notifications['lowStockAlerts'] = (bool) ($notifications['lowStockAlerts'] ?? false);
        $notifications['newOrderAlerts'] = (bool) ($notifications['newOrderAlerts'] ?? false);
        $notifications['digestFrequency'] = strtolower($notifications['digestFrequency'] ?? 'daily');
        $notifications['escalationEmail'] = $notifications['escalationEmail'] ?? null;

        return [
            'general' => $general,
            'notifications' => $notifications,
            'updatedAt' => Arr::get(
                $settings,
                'updatedAt',
                $tenant->updated_at?->toIso8601String()
            ),
        ];
    }

    private function defaultGeneral(?Tenant $tenant): array
    {
        $slug = $tenant?->name ? Str::slug($tenant->name) : 'paradise-pos';

        return [
            'businessName' => $tenant?->name ?? 'Paradise POS Tenant',
            'businessEmail' => 'admin@'.$slug.'.com',
            'businessPhone' => $tenant?->phone,
            'timezone' => data_get($tenant?->settings, 'timezone', 'Asia/Colombo'),
            'currency' => data_get($tenant?->settings, 'currency', 'LKR'),
            'locale' => data_get($tenant?->settings, 'language', 'en-US'),
            'invoicePrefix' => 'INV',
            'invoiceStartNumber' => 1000,
            'defaultSiteId' => null,
            'taxRate' => 0.0,
        ];
    }

    private function defaultNotifications(): array
    {
        return [
            'sendDailySummary' => true,
            'lowStockAlerts' => true,
            'newOrderAlerts' => true,
            'digestFrequency' => 'daily',
            'escalationEmail' => null,
        ];
    }
}
