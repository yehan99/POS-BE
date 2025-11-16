<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StockTransferItem */
class StockTransferItemResource extends JsonResource
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
            'productId' => $this->product_id,
            'productName' => $this->product_name,
            'productSku' => $this->product_sku,
            'quantity' => (int) $this->quantity,
            'receivedQuantity' => (int) $this->received_quantity,
            'unitCost' => (float) $this->unit_cost,
            'totalCost' => (float) $this->total_cost,
        ];
    }
}
