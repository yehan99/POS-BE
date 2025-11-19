<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportsDashboardService;

class ReportsDashboardController extends Controller
{
    public function __construct(private readonly ReportsDashboardService $reportsDashboardService)
    {
    }

    public function summary()
    {
        return response()->json(
            $this->reportsDashboardService->summary()
        );
    }
}
