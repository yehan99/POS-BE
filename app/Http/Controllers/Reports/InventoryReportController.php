<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\InventoryReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryReportController extends Controller
{
    public function __construct(private readonly InventoryReportService $inventoryReportService)
    {
    }

    public function show(Request $request)
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in([
                'today',
                'yesterday',
                'this_week',
                'last_week',
                'this_month',
                'last_month',
                'this_year',
                'last_year',
                'custom',
            ])],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'productId' => ['nullable', 'string'],
            'categoryId' => ['nullable', 'string'],
            'locationId' => ['nullable', 'string'],
        ]);

        $validated['period'] = $validated['period'] ?? 'this_month';

        return response()->json(
            $this->inventoryReportService->generate($validated)
        );
    }
}
