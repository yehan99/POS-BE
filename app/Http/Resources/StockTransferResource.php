<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StockTransfer */
class StockTransferResource extends JsonResource
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
            'transferNumber' => $this->transfer_number,
            'fromLocationId' => $this->from_location_id,
            'fromLocationName' => $this->fromLocation?->name,
            'fromLocationCode' => $this->fromLocation?->code,
            'toLocationId' => $this->to_location_id,
            'toLocationName' => $this->toLocation?->name,
            'toLocationCode' => $this->toLocation?->code,
            'status' => $this->status,
            'totalItems' => (int) $this->total_items,
            'totalValue' => (float) $this->total_value,
            'requestedBy' => $this->requested_by,
            'approvedBy' => $this->approved_by,
            'shippedBy' => $this->shipped_by,
            'receivedBy' => $this->received_by,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toIso8601String(),
            'approvedAt' => $this->approved_at?->toIso8601String(),
            'shippedAt' => $this->shipped_at?->toIso8601String(),
            'receivedAt' => $this->received_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'cancelReason' => $this->cancel_reason,
            'items' => StockTransferItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
