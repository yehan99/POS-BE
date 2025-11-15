<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Customer */
class CustomerResource extends JsonResource
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
            'customerCode' => $this->customer_code,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'dateOfBirth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'address' => $this->address ?? [
                'street' => null,
                'city' => null,
                'state' => null,
                'postalCode' => null,
                'country' => null,
            ],
            'loyaltyPoints' => $this->loyalty_points,
            'loyaltyTier' => $this->loyalty_tier,
            'totalPurchases' => $this->total_purchases,
            'totalSpent' => (float) $this->total_spent,
            'lastPurchaseDate' => $this->last_purchase_at?->toDateTimeString(),
            'notes' => $this->notes,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toDateTimeString(),
            'updatedAt' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
