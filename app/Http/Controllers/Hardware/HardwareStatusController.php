<?php

namespace App\Http\Controllers\Hardware;

use App\Http\Controllers\Controller;
use App\Services\HardwareStatusService;
use Illuminate\Http\JsonResponse;

class HardwareStatusController extends Controller
{
    protected HardwareStatusService $statusService;

    public function __construct(HardwareStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    /**
     * Get system health metrics
     *
     * @return JsonResponse
     */
    public function systemHealth(): JsonResponse
    {
        try {
            $health = $this->statusService->getSystemHealth();

            return response()->json([
                'success' => true,
                'data' => $health,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system health',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get device health details
     *
     * @return JsonResponse
     */
    public function deviceHealth(): JsonResponse
    {
        try {
            $deviceHealth = $this->statusService->getDeviceHealth();

            return response()->json([
                'success' => true,
                'data' => $deviceHealth,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve device health',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get device alerts
     *
     * @return JsonResponse
     */
    public function alerts(): JsonResponse
    {
        try {
            $alerts = $this->statusService->getDeviceAlerts();

            return response()->json([
                'success' => true,
                'data' => $alerts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alerts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent device events
     *
     * @return JsonResponse
     */
    public function events(): JsonResponse
    {
        try {
            $limit = request()->query('limit', 50);
            $events = $this->statusService->getRecentEvents((int) $limit);

            return response()->json([
                'success' => true,
                'data' => $events,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve events',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics by device type
     *
     * @return JsonResponse
     */
    public function statisticsByType(): JsonResponse
    {
        try {
            $statistics = $this->statusService->getStatisticsByType();

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get complete dashboard data
     *
     * @return JsonResponse
     */
    public function dashboard(): JsonResponse
    {
        try {
            $data = [
                'system_health' => $this->statusService->getSystemHealth(),
                'device_health' => $this->statusService->getDeviceHealth(),
                'alerts' => $this->statusService->getDeviceAlerts(),
                'recent_events' => $this->statusService->getRecentEvents(20),
                'statistics_by_type' => $this->statusService->getStatisticsByType(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
