<?php

namespace App\Http\Requests\Inventory\StockAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class RejectStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
