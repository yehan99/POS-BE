<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryLocationResource;
use App\Models\InventoryLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryLocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InventoryLocation::query();

        if ($request->has('isActive')) {
            $query->where('is_active', filter_var($request->input('isActive'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE));
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('manager', 'like', "%{$search}%");
            });
        }

        $locations = $query->orderBy('name')->get();

        return response()->json(
            InventoryLocationResource::collection($locations)
        );
    }

    public function store(Request $request): InventoryLocationResource
    {
        $data = $this->validateLocationPayload($request);

        $location = InventoryLocation::create($data);

        return InventoryLocationResource::make($location);
    }

    public function show(InventoryLocation $location): InventoryLocationResource
    {
        return InventoryLocationResource::make($location);
    }

    public function update(Request $request, InventoryLocation $location): InventoryLocationResource
    {
        $data = $this->validateLocationPayload($request, $location->id);

        $location->fill($data);
        $location->save();

        return InventoryLocationResource::make($location);
    }

    private function validateLocationPayload(Request $request, ?string $locationId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', Rule::unique('inventory_locations', 'code')->ignore($locationId)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'array'],
            'isActive' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'currentUtilization' => ['nullable', 'integer', 'min:0'],
            'manager' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
        ]);

        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'address' => $data['address'] ?? null,
            'is_active' => $data['isActive'] ?? true,
            'capacity' => $data['capacity'] ?? null,
            'current_utilization' => $data['currentUtilization'] ?? null,
            'manager' => $data['manager'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
        ];
    }
}
