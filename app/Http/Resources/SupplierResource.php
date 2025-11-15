<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Supplier */
class SupplierResource extends JsonResource
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
            'supplierCode' => $this->supplier_code,
            'code' => $this->supplier_code,
            'name' => $this->name,
            'contactPerson' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'category' => $this->category,
            'status' => $this->status,
            'isActive' => (bool) $this->is_active,
            'isPreferred' => (bool) $this->is_preferred,
            'paymentTerms' => $this->payment_terms,
            'creditLimit' => $this->credit_limit !== null ? (float) $this->credit_limit : null,
            'taxId' => $this->tax_id,
            'website' => $this->website,
            'address' => $this->address ?? [
                'street' => null,
                'city' => null,
                'state' => null,
                'postalCode' => null,
                'country' => null,
            ],
            'bankDetails' => $this->bank_details ?? [
                'bankName' => null,
                'accountNumber' => null,
                'accountName' => null,
                'branchCode' => null,
                'swiftCode' => null,
            ],
            'notes' => $this->notes,
            'rating' => $this->rating !== null ? (float) $this->rating : null,
            'totalPurchases' => $this->total_purchases,
            'totalOrders' => $this->total_orders,
            'totalSpent' => (float) $this->total_spent,
            'spendThisMonth' => (float) $this->spend_this_month,
            'spendLastMonth' => (float) $this->spend_last_month,
            'onTimeDeliveryRate' => (float) $this->on_time_delivery_rate,
            'averageLeadTimeDays' => (float) $this->average_lead_time_days,
            'lastPurchaseDate' => $this->last_purchase_at?->toDateTimeString(),
            'monthlySpendStats' => $this->monthly_spend_stats ?? [],
            'createdAt' => $this->created_at?->toDateTimeString(),
            'updatedAt' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
