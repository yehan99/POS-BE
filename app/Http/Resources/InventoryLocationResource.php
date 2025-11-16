<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InventoryLocation */
class InventoryLocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'address' => $this->address ?? [],
            'isActive' => (bool) $this->is_active,
            'capacity' => $this->capacity !== null ? (int) $this->capacity : null,
            'currentUtilization' => $this->current_utilization !== null ? (int) $this->current_utilization : null,
            'manager' => $this->manager,
            'phone' => $this->phone,
            'email' => $this->email,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
