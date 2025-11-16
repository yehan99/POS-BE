<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $supplier = $this->whenLoaded('supplier');

        return [
            'id' => $this->id,
            'poNumber' => $this->po_number,
            'supplierId' => $this->supplier_id,
            'supplierName' => $supplier?->name,
            'supplierCode' => $supplier?->supplier_code,
            'status' => $this->status,
            'paymentStatus' => $this->payment_status,
            'paymentMethod' => $this->payment_method,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'shippingCost' => (float) $this->shipping_cost,
            'total' => (float) $this->total,
            'expectedDeliveryDate' => $this->expected_delivery_date?->toDateString(),
            'actualDeliveryDate' => $this->actual_delivery_date?->toDateString(),
            'paymentDueDate' => $this->meta['paymentDueDate'] ?? null,
            'createdBy' => $this->created_by,
            'approvedBy' => $this->approved_by,
            'receivedBy' => $this->received_by,
            'notes' => $this->notes,
            'termsAndConditions' => $this->terms_and_conditions,
            'createdAt' => $this->created_at?->toIso8601String(),
            'approvedAt' => $this->approved_at?->toIso8601String(),
            'orderedAt' => $this->ordered_at?->toIso8601String(),
            'receivedAt' => $this->received_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'cancelReason' => $this->cancel_reason,
            'items' => $this->items->map(static function ($item) {
                return [
                    'id' => $item->id,
                    'productId' => $item->product_id,
                    'productName' => $item->product_name,
                    'productSku' => $item->product_sku,
                    'quantity' => (int) $item->quantity,
                    'receivedQuantity' => (int) $item->received_quantity,
                    'unitCost' => (float) $item->unit_cost,
                    'tax' => (float) $item->tax,
                    'discount' => (float) $item->discount,
                    'total' => (float) $item->total,
                ];
            }),
        ];
    }
}
