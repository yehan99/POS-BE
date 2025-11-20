<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission('settings.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'businessName' => $this->input('businessName', $this->input('business_name')),
            'businessEmail' => $this->input('businessEmail', $this->input('business_email')),
            'businessPhone' => $this->normalizeNullableString('businessPhone', 'business_phone'),
            'timezone' => $this->input('timezone'),
            'currency' => $this->sanitizeCurrency(),
            'locale' => $this->input('locale'),
            'invoicePrefix' => $this->sanitizeInvoicePrefix(),
            'invoiceStartNumber' => $this->input('invoiceStartNumber', $this->input('invoice_start_number')),
            'taxRate' => $this->normalizeTaxRate(),
            'defaultSiteId' => $this->normalizeNullableString('defaultSiteId', 'default_site_id'),
        ]);
    }

    public function rules(): array
    {
        $user = $this->user();

        $siteRule = Rule::exists('sites', 'id');

        if ($user) {
            $siteRule = $siteRule->where(function ($query) use ($user) {
                $query->whereNull('tenant_id')
                    ->orWhere('tenant_id', $user->tenant_id);
            });
        }

        return [
            'businessName' => ['required', 'string', 'max:180'],
            'businessEmail' => ['required', 'email', 'max:190'],
            'businessPhone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['required', 'string', 'max:80'],
            'currency' => ['required', 'string', 'size:3'],
            'locale' => ['required', 'string', 'max:12'],
            'invoicePrefix' => ['required', 'string', 'max:6'],
            'invoiceStartNumber' => ['required', 'integer', 'min:1', 'max:999999'],
            'taxRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'defaultSiteId' => ['nullable', 'string', $siteRule],
        ];
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

    private function sanitizeInvoicePrefix(): ?string
    {
        $value = $this->input('invoicePrefix', $this->input('invoice_prefix'));

        if ($value === null) {
            return null;
        }

        return Str::upper(trim((string) $value));
    }

    private function sanitizeCurrency(): ?string
    {
        $value = $this->input('currency');

        if ($value === null) {
            return null;
        }

        return Str::upper(trim((string) $value));
    }

    private function normalizeTaxRate(): ?float
    {
        $value = $this->input('taxRate', $this->input('tax_rate'));

        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
