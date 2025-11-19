<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CustomerReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerReportController extends Controller
{
    public function __construct(private readonly CustomerReportService $customerReportService)
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
            'customerId' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $validated['period'] = $validated['period'] ?? 'this_month';

        return response()->json(
            $this->customerReportService->generate($validated)
        );
    }
}
