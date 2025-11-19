<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission('settings.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sendDailySummary' => $this->input('sendDailySummary', $this->input('send_daily_summary', false)),
            'lowStockAlerts' => $this->input('lowStockAlerts', $this->input('low_stock_alerts', false)),
            'newOrderAlerts' => $this->input('newOrderAlerts', $this->input('new_order_alerts', false)),
            'digestFrequency' => $this->normalizeFrequency(),
            'escalationEmail' => $this->normalizeNullableString('escalationEmail', 'escalation_email'),
        ]);
    }

    public function rules(): array
    {
        return [
            'sendDailySummary' => ['required', 'boolean'],
            'lowStockAlerts' => ['required', 'boolean'],
            'newOrderAlerts' => ['required', 'boolean'],
            'digestFrequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'escalationEmail' => ['nullable', 'email', 'max:190'],
        ];
    }

    private function normalizeFrequency(): ?string
    {
        $value = $this->input('digestFrequency', $this->input('digest_frequency'));

        if ($value === null) {
            return null;
        }

        return strtolower(trim((string) $value));
    }

    private function normalizeNullableString(string $primary, ?string $fallback = null): ?string
    {
        $value = $this->input($primary, $fallback ? $this->input($fallback) : null);

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
