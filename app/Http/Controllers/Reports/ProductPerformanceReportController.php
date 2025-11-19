<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ProductPerformanceReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductPerformanceReportController extends Controller
{
    public function __construct(private readonly ProductPerformanceReportService $productPerformanceReportService)
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
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $validated['period'] = $validated['period'] ?? 'this_month';

        return response()->json(
            $this->productPerformanceReportService->generate($validated)
        );
    }
}
