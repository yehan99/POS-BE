<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\StockAlertsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAlertsController extends Controller
{
    protected StockAlertsService $alertsService;

    public function __construct(StockAlertsService $alertsService)
    {
        $this->alertsService = $alertsService;
    }

    /**
     * Get paginated stock alerts with filters
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'type' => $request->input('type'),
            'status' => $request->input('status'),
            'severity' => $request->input('severity'),
            'location_id' => $request->input('location_id'),
            'page' => $request->input('page', 1),
            'per_page' => $request->input('pageSize', 10),
        ];

        $result = $this->alertsService->getAlerts($filters);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total_pages' => $result['total_pages'],
        ]);
    }

    /**
     * Acknowledge a specific alert
     */
    public function acknowledge(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'acknowledged_by' => 'sometimes|string',
        ]);

        $alert = $this->alertsService->acknowledgeAlert(
            $id,
            $request->input('acknowledged_by', $request->user()->name ?? 'System')
        );

        if (!$alert) {
            return response()->json([
                'success' => false,
                'message' => 'Alert not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged successfully',
            'data' => $alert,
        ]);
    }

    /**
     * Resolve a specific alert
     */
    public function resolve(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'resolved_by' => 'sometimes|string',
            'resolution_notes' => 'sometimes|string',
        ]);

        $alert = $this->alertsService->resolveAlert(
            $id,
            $request->input('resolved_by', $request->user()->name ?? 'System'),
            $request->input('resolution_notes')
        );

        if (!$alert) {
            return response()->json([
                'success' => false,
                'message' => 'Alert not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved successfully',
            'data' => $alert,
        ]);
    }

    /**
     * Bulk resolve alerts
     */
    public function bulkResolve(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|string',
            'resolved_by' => 'sometimes|string',
            'resolution_notes' => 'sometimes|string',
        ]);

        $count = $this->alertsService->bulkResolveAlerts(
            $request->input('ids'),
            $request->input('resolved_by', $request->user()->name ?? 'System'),
            $request->input('resolution_notes')
        );

        return response()->json([
            'success' => true,
            'message' => "{$count} alerts resolved successfully",
            'count' => $count,
        ]);
    }

    /**
     * Get alert statistics/summary
     */
    public function summary(): JsonResponse
    {
        $summary = $this->alertsService->getAlertSummary();

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
