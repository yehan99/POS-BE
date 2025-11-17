<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StockAdjustment */
class StockAdjustmentResource extends JsonResource
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
            'adjustmentNumber' => $this->adjustment_number,
            'productId' => $this->product_id,
            'productName' => $this->product_name ?? $this->product?->name,
            'productSku' => $this->product_sku ?? $this->product?->sku,
            'adjustmentType' => $this->adjustment_type,
            'quantity' => (int) $this->quantity,
            'previousStock' => (int) $this->previous_stock,
            'newStock' => (int) $this->new_stock,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'cost' => (float) $this->unit_cost,
            'totalValue' => (float) $this->total_value,
            'netQuantity' => (int) $this->quantity_change,
            'netValue' => (float) $this->value_change,
            'locationId' => $this->location_id,
            'locationName' => $this->location?->name,
            'status' => $this->status,
            'createdBy' => $this->created_by,
            'approvedBy' => $this->approved_by,
            'approvedAt' => $this->approved_at?->toIso8601String(),
            'rejectedBy' => $this->rejected_by,
            'rejectionReason' => $this->rejection_reason,
            'rejectedAt' => $this->rejected_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
