<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLoyaltyTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerLoyaltyService
{
    public function recordTransaction(Customer $customer, array $data): CustomerLoyaltyTransaction
    {
        return DB::transaction(function () use ($customer, $data) {
            $latest = $customer->loyaltyTransactions()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $startingPoints = $latest?->points_balance ?? (int) $customer->loyalty_points;
            $startingSpent = $latest?->total_spent_balance ?? (float) $customer->total_spent;
            $startingPurchases = $latest?->purchases_balance ?? (int) $customer->total_purchases;

            $type = $data['type'];
            $pointsDelta = (int) ($data['pointsDelta'] ?? 0);
            $totalSpentDelta = isset($data['totalSpentDelta']) ? (float) $data['totalSpentDelta'] : 0.0;
            $purchasesDelta = (int) ($data['purchasesDelta'] ?? 0);

            if ($type === 'earned' && $pointsDelta < 0) {
                $pointsDelta = abs($pointsDelta);
            }

            if ($type === 'redeemed' && $pointsDelta > 0) {
                $pointsDelta = -$pointsDelta;
            }

            // Normalize deltas so redemptions always subtract and earnings add points.
            if ($type === 'earned') {
                $pointsDelta = abs($pointsDelta);
            }

            if ($type === 'redeemed') {
                $pointsDelta = -abs($pointsDelta);
            }

            // Cap adjustments so balances never become negative even if the delta overshoots.
            if ($startingPoints + $pointsDelta < 0) {
                $pointsDelta = -$startingPoints;
            }

            $pointsBalance = $startingPoints + $pointsDelta;

            // Ensure spend and purchase aggregates remain within valid bounds.
            $desiredSpent = $startingSpent + $totalSpentDelta;
            if ($desiredSpent < 0) {
                $totalSpentDelta = -$startingSpent;
                $desiredSpent = 0;
            }
            $totalSpentBalance = $desiredSpent;

            $desiredPurchases = $startingPurchases + $purchasesDelta;
            if ($desiredPurchases < 0) {
                $purchasesDelta = -$startingPurchases;
                $desiredPurchases = 0;
            }
            $purchasesBalance = $desiredPurchases;

            $transaction = $customer->loyaltyTransactions()->create([
                'type' => $type,
                'points_delta' => $pointsDelta,
                'points_balance' => $pointsBalance,
                'total_spent_delta' => $totalSpentDelta,
                'total_spent_balance' => $totalSpentBalance,
                'purchases_delta' => $purchasesDelta,
                'purchases_balance' => $purchasesBalance,
                'reason' => $data['reason'] ?? null,
                'meta' => $data['meta'] ?? null,
            ]);

            $customer->forceFill([
                'loyalty_points' => $pointsBalance,
                'total_spent' => $totalSpentBalance,
                'total_purchases' => $purchasesBalance,
                'last_purchase_at' => $this->calculateLastPurchaseAt($customer, $transaction, $totalSpentDelta, $purchasesDelta),
            ])->save();

            return $transaction;
        });
    }

    protected function calculateLastPurchaseAt(Customer $customer, CustomerLoyaltyTransaction $transaction, float $totalSpentDelta, int $purchasesDelta): ?Carbon
    {
        if ($transaction->type === 'earned' && ($totalSpentDelta > 0 || $purchasesDelta > 0)) {
            return Carbon::now();
        }

        if ($transaction->type === 'adjusted' && ($totalSpentDelta > 0 || $purchasesDelta > 0)) {
            return Carbon::now();
        }

        return $customer->last_purchase_at;
    }
}
