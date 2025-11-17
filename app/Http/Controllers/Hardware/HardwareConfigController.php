<?php

namespace App\Http\Controllers\Hardware;

use App\Http\Controllers\Controller;
use App\Models\HardwareDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HardwareConfigController extends Controller
{
    /**
     * Get all hardware devices with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = HardwareDevice::query();

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('manufacturer', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $devices = $query->get();

        return response()->json([
            'success' => true,
            'data' => $devices,
            'total' => $devices->count(),
        ]);
    }

    /**
     * Get a specific hardware device
     */
    public function show(string $id): JsonResponse
    {
        $device = HardwareDevice::find($id);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $device,
        ]);
    }

    /**
     * Create a new hardware device
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:PRINTER,SCANNER,CASH_DRAWER,PAYMENT_TERMINAL,CUSTOMER_DISPLAY,WEIGHT_SCALE',
            'connection_type' => 'required|in:USB,NETWORK,BLUETOOTH,SERIAL,KEYBOARD_WEDGE',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'ip_address' => 'nullable|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'enabled' => 'boolean',
            'config' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $device = HardwareDevice::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Device created successfully',
            'data' => $device,
        ], 201);
    }

    /**
     * Update an existing hardware device
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $device = HardwareDevice::find($id);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:PRINTER,SCANNER,CASH_DRAWER,PAYMENT_TERMINAL,CUSTOMER_DISPLAY,WEIGHT_SCALE',
            'connection_type' => 'sometimes|required|in:USB,NETWORK,BLUETOOTH,SERIAL,KEYBOARD_WEDGE',
            'status' => 'sometimes|required|in:CONNECTED,DISCONNECTED,ERROR,CONNECTING',
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'ip_address' => 'nullable|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'enabled' => 'boolean',
            'error' => 'nullable|string',
            'config' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $device->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Device updated successfully',
            'data' => $device->fresh(),
        ]);
    }

    /**
     * Delete a hardware device
     */
    public function destroy(string $id): JsonResponse
    {
        $device = HardwareDevice::find($id);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
            ], 404);
        }

        $device->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device deleted successfully',
        ]);
    }

    /**
     * Test device connection
     */
    public function testConnection(string $id): JsonResponse
    {
        $device = HardwareDevice::find($id);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
            ], 404);
        }

        // Simulate connection test
        // In production, this would actually test the device connection
        $success = rand(0, 1) === 1;

        if ($success) {
            $device->markAsConnected();
            $device->incrementOperations();

            return response()->json([
                'success' => true,
                'message' => 'Device connected successfully',
                'data' => $device->fresh(),
            ]);
        } else {
            $device->markAsError('Connection test failed');

            return response()->json([
                'success' => false,
                'message' => 'Device connection failed',
                'data' => $device->fresh(),
            ], 500);
        }
    }

    /**
     * Toggle device enabled status
     */
    public function toggleEnabled(string $id): JsonResponse
    {
        $device = HardwareDevice::find($id);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found',
            ], 404);
        }

        $device->update(['enabled' => !$device->enabled]);

        return response()->json([
            'success' => true,
            'message' => 'Device status toggled successfully',
            'data' => $device->fresh(),
        ]);
    }

    /**
     * Get connection status summary
     */
    public function connectionStatus(): JsonResponse
    {
        $total = HardwareDevice::count();
        $connected = HardwareDevice::where('status', HardwareDevice::STATUS_CONNECTED)->count();
        $disconnected = HardwareDevice::where('status', HardwareDevice::STATUS_DISCONNECTED)->count();
        $error = HardwareDevice::where('status', HardwareDevice::STATUS_ERROR)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'connected' => $connected,
                'disconnected' => $disconnected,
                'error' => $error,
            ],
        ]);
    }

    /**
     * Clear all devices
     */
    public function clearAll(): JsonResponse
    {
        $count = HardwareDevice::count();
        HardwareDevice::query()->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} device(s) cleared successfully",
            'count' => $count,
        ]);
    }

    /**
     * Bulk delete devices
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:hardware_devices,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $count = HardwareDevice::whereIn('id', $request->input('ids'))->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} device(s) deleted successfully",
            'count' => $count,
        ]);
    }
}
