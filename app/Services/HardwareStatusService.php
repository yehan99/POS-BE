<?php

namespace App\Services;

use App\Models\HardwareDevice;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class HardwareStatusService
{
    /**
     * Get system health metrics
     */
    public function getSystemHealth(): array
    {
        $devices = HardwareDevice::all();

        $totalDevices = $devices->count();
        $connectedDevices = $devices->where('status', 'CONNECTED')->count();
        $disconnectedDevices = $devices->where('status', 'DISCONNECTED')->count();
        $errorDevices = $devices->where('status', 'ERROR')->count();

        $totalOperations = $devices->sum('operations_count');
        $totalErrors = $devices->sum('error_count');

        // Calculate overall health score (0-100)
        $overallHealth = $this->calculateOverallHealth($devices);

        return [
            'overall' => $overallHealth,
            'total_devices' => $totalDevices,
            'connected_devices' => $connectedDevices,
            'disconnected_devices' => $disconnectedDevices,
            'error_devices' => $errorDevices,
            'total_operations' => $totalOperations,
            'total_errors' => $totalErrors,
            'average_response_time' => $this->calculateAverageResponseTime($devices),
            'system_uptime' => $this->getSystemUptime(),
        ];
    }

    /**
     * Get device health details
     */
    public function getDeviceHealth(): Collection
    {
        return HardwareDevice::all()->map(function ($device) {
            return [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'type' => $device->type,
                'status' => $device->status,
                'uptime' => $this->calculateUptime($device),
                'last_activity' => $device->last_connected,
                'operations_count' => $device->operations_count ?? 0,
                'error_count' => $device->error_count ?? 0,
                'health_score' => $this->calculateDeviceHealthScore($device),
                'response_time' => $this->calculateResponseTime($device),
            ];
        });
    }

    /**
     * Get device alerts
     */
    public function getDeviceAlerts(): Collection
    {
        $alerts = collect();

        HardwareDevice::all()->each(function ($device) use ($alerts) {
            // Check for errors
            if ($device->status === 'ERROR') {
                $alerts->push([
                    'id' => 'alert_' . $device->id . '_error',
                    'type' => 'error',
                    'device' => $device->name,
                    'message' => $device->error ?? 'Device connection error',
                    'timestamp' => $device->updated_at,
                    'acknowledged' => false,
                ]);
            }

            // Check for high error rate
            if (($device->error_count ?? 0) > 10 && ($device->operations_count ?? 1) > 0) {
                $errorRate = ($device->error_count / $device->operations_count) * 100;
                if ($errorRate > 20) {
                    $alerts->push([
                        'id' => 'alert_' . $device->id . '_error_rate',
                        'type' => 'warning',
                        'device' => $device->name,
                        'message' => sprintf('High error rate: %.1f%%', $errorRate),
                        'timestamp' => $device->updated_at,
                        'acknowledged' => false,
                    ]);
                }
            }

            // Check for disconnected devices
            if ($device->status === 'DISCONNECTED' && $device->enabled) {
                $alerts->push([
                    'id' => 'alert_' . $device->id . '_disconnected',
                    'type' => 'warning',
                    'device' => $device->name,
                    'message' => 'Device is disconnected',
                    'timestamp' => $device->updated_at,
                    'acknowledged' => false,
                ]);
            }

            // Check for inactive devices (no activity in last 24 hours)
            if ($device->last_connected && Carbon::parse($device->last_connected)->diffInHours(now()) > 24) {
                $alerts->push([
                    'id' => 'alert_' . $device->id . '_inactive',
                    'type' => 'info',
                    'device' => $device->name,
                    'message' => 'No activity in last 24 hours',
                    'timestamp' => $device->last_connected,
                    'acknowledged' => false,
                ]);
            }
        });

        return $alerts->sortByDesc('timestamp')->values();
    }

    /**
     * Get recent device events
     */
    public function getRecentEvents(int $limit = 50): Collection
    {
        $events = collect();

        // Get recently updated devices
        $devices = HardwareDevice::orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($devices as $device) {
            // Determine event type based on status
            $eventType = match($device->status) {
                'CONNECTED' => 'DEVICE_CONNECTED',
                'DISCONNECTED' => 'DEVICE_DISCONNECTED',
                'ERROR' => 'DEVICE_ERROR',
                'CONNECTING' => 'DEVICE_CONNECTING',
                default => 'DEVICE_STATUS_CHANGE',
            };

            $events->push([
                'type' => $eventType,
                'device_id' => $device->id,
                'device_name' => $device->name,
                'timestamp' => $device->updated_at,
                'error' => $device->status === 'ERROR' ? $device->error : null,
            ]);
        }

        return $events;
    }

    /**
     * Get device statistics by type
     */
    public function getStatisticsByType(): array
    {
        $devices = HardwareDevice::all()->groupBy('type');

        $statistics = [];
        foreach ($devices as $type => $typeDevices) {
            $statistics[$type] = [
                'total' => $typeDevices->count(),
                'connected' => $typeDevices->where('status', 'CONNECTED')->count(),
                'disconnected' => $typeDevices->where('status', 'DISCONNECTED')->count(),
                'error' => $typeDevices->where('status', 'ERROR')->count(),
                'total_operations' => $typeDevices->sum('operations_count'),
                'total_errors' => $typeDevices->sum('error_count'),
            ];
        }

        return $statistics;
    }

    /**
     * Calculate overall system health score
     */
    private function calculateOverallHealth(Collection $devices): int
    {
        if ($devices->isEmpty()) {
            return 100;
        }

        $totalDevices = $devices->count();
        $connectedDevices = $devices->where('status', 'CONNECTED')->count();
        $errorDevices = $devices->where('status', 'ERROR')->count();

        // Base score on connection percentage
        $connectionScore = ($connectedDevices / $totalDevices) * 100;

        // Reduce score based on errors
        $errorPenalty = ($errorDevices / $totalDevices) * 30;

        // Calculate error rate penalty
        $totalOperations = $devices->sum('operations_count') ?: 1;
        $totalErrors = $devices->sum('error_count');
        $errorRate = ($totalErrors / $totalOperations) * 100;
        $errorRatePenalty = min($errorRate * 2, 20);

        $overallHealth = max(0, min(100, $connectionScore - $errorPenalty - $errorRatePenalty));

        return (int) round($overallHealth);
    }

    /**
     * Calculate device health score
     */
    private function calculateDeviceHealthScore(HardwareDevice $device): int
    {
        $healthScore = 100;

        // Status penalties
        if ($device->status === 'DISCONNECTED') {
            $healthScore = 0;
        } elseif ($device->status === 'ERROR') {
            $healthScore = 20;
        } elseif ($device->status === 'CONNECTING') {
            $healthScore = 50;
        }

        // Error rate penalty
        if ($device->operations_count > 0 && $device->error_count > 0) {
            $errorRate = ($device->error_count / $device->operations_count) * 100;
            $healthScore -= min($errorRate * 2, 30);
        }

        // Inactivity penalty
        if ($device->last_connected) {
            $hoursInactive = Carbon::parse($device->last_connected)->diffInHours(now());
            if ($hoursInactive > 24) {
                $healthScore -= min($hoursInactive, 20);
            }
        }

        return max(0, min(100, (int) round($healthScore)));
    }

    /**
     * Calculate device uptime in minutes
     */
    private function calculateUptime(HardwareDevice $device): int
    {
        if (!$device->last_connected) {
            return 0;
        }

        return Carbon::parse($device->last_connected)->diffInMinutes(now());
    }

    /**
     * Calculate average response time (simulated for demo)
     */
    private function calculateResponseTime(HardwareDevice $device): ?int
    {
        if ($device->status !== 'CONNECTED') {
            return null;
        }

        // Simulate response time based on device type and status
        // In production, this would come from actual monitoring data
        $baseTime = match($device->type) {
            'PRINTER' => 25,
            'SCANNER' => 15,
            'PAYMENT_TERMINAL' => 35,
            'CASH_DRAWER' => 10,
            default => 20,
        };

        // Add some variance
        return $baseTime + rand(-5, 10);
    }

    /**
     * Calculate average response time across all devices
     */
    private function calculateAverageResponseTime(Collection $devices): int
    {
        $connectedDevices = $devices->where('status', 'CONNECTED');

        if ($connectedDevices->isEmpty()) {
            return 0;
        }

        $totalResponseTime = $connectedDevices->sum(function ($device) {
            return $this->calculateResponseTime($device) ?? 0;
        });

        return (int) round($totalResponseTime / $connectedDevices->count());
    }

    /**
     * Get system uptime in minutes (since first device connection)
     */
    private function getSystemUptime(): int
    {
        $firstDevice = HardwareDevice::orderBy('created_at')->first();

        if (!$firstDevice) {
            return 0;
        }

        return Carbon::parse($firstDevice->created_at)->diffInMinutes(now());
    }
}
