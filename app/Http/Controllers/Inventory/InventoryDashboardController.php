<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\InventoryDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryDashboardController extends Controller
{
    public function __construct(
        private readonly InventoryDashboardService $dashboardService
    ) {}

    /**
     * Get dashboard metrics (snapshot tiles)
     */
    public function metrics(Request $request): JsonResponse
    {
        $metrics = $this->dashboardService->getMetrics();

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get pipeline items (operations in progress)
     */
    public function pipeline(Request $request): JsonResponse
    {
        $pipeline = $this->dashboardService->getPipelineItems();

        return response()->json([
            'success' => true,
            'data' => $pipeline,
        ]);
    }

    /**
     * Get exception counts
     */
    public function exceptions(Request $request): JsonResponse
    {
        $exceptions = $this->dashboardService->getExceptions();

        return response()->json([
            'success' => true,
            'data' => $exceptions,
        ]);
    }

    /**
     * Get stock alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 5), 50);
        $alerts = $this->dashboardService->getAlerts($limit);

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Get all dashboard data at once
     */
    public function index(Request $request): JsonResponse
    {
        $alertLimit = min((int) $request->input('alert_limit', 5), 50);

        $data = [
            'metrics' => $this->dashboardService->getMetrics(),
            'pipeline' => $this->dashboardService->getPipelineItems(),
            'exceptions' => $this->dashboardService->getExceptions(),
            'alerts' => $this->dashboardService->getAlerts($alertLimit),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
