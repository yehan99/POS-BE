<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerLoyaltyTransactionResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customer_id,
            'type' => $this->type,
            'pointsDelta' => $this->points_delta,
            'pointsBalance' => $this->points_balance,
            'totalSpentDelta' => (float) $this->total_spent_delta,
            'totalSpentBalance' => (float) $this->total_spent_balance,
            'purchasesDelta' => $this->purchases_delta,
            'purchasesBalance' => $this->purchases_balance,
            'reason' => $this->reason,
            'meta' => $this->meta,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
