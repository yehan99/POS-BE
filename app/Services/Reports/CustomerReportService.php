<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\SalesTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerReportService
{
    public function __construct(
        private readonly Customer $customer,
        private readonly SalesTransaction $salesTransaction,
    ) {
    }

    public function generate(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $customersQuery = $this->customer->newQuery();

        if (! empty($filters['customerId'])) {
            $customersQuery->where('id', $filters['customerId']);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $customersQuery->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $customersQuery->where('is_active', false);
            }
        }

        $customers = $customersQuery->get();

        if ($customers->isEmpty()) {
            return $this->emptyReport();
        }

        $customerIds = $customers->pluck('id')->filter()->values()->all();
        $customerMap = $customers->keyBy('id');

        $newCustomers = $customers->filter(function (Customer $customer) use ($startDate, $endDate) {
            if (! $customer->created_at) {
                return false;
            }

            return $customer->created_at->between($startDate, $endDate);
        })->count();

        $recentWindow = $endDate->copy()->subDays(90);
        $activeCustomers = $customers->filter(function (Customer $customer) use ($recentWindow) {
            if ($customer->is_active) {
                return true;
            }

            if ($customer->last_purchase_at instanceof Carbon) {
                return $customer->last_purchase_at->greaterThanOrEqualTo($recentWindow);
            }

            return false;
        })->count();

        $totalCustomers = $customers->count();
        $inactiveCustomers = max($totalCustomers - $activeCustomers, 0);

        $transactionsQuery = $this->salesTransaction->newQuery()
            ->completed()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereNotNull('customer_id');

        if (! empty($customerIds)) {
            $transactionsQuery->whereIn('customer_id', $customerIds);
        }

        $transactions = $transactionsQuery
            ->orderBy('transaction_date')
            ->get(['customer_id', 'transaction_date', 'total']);

        $customerStats = [];
        $totalRevenue = 0.0;

        foreach ($transactions as $transaction) {
            $customerId = (string) $transaction->customer_id;
            if ($customerId === '') {
                continue;
            }

            $amount = (float) $transaction->total;
            $totalRevenue += $amount;

            if (! isset($customerStats[$customerId])) {
                $customerStats[$customerId] = [
                    'totalSpent' => 0.0,
                    'totalPurchases' => 0,
                    'lastPurchase' => null,
                ];
            }

            $customerStats[$customerId]['totalSpent'] += $amount;
            $customerStats[$customerId]['totalPurchases']++;

            $currentLastPurchase = $customerStats[$customerId]['lastPurchase'];
            if ($currentLastPurchase === null || $transaction->transaction_date->gt($currentLastPurchase)) {
                $customerStats[$customerId]['lastPurchase'] = $transaction->transaction_date->copy();
            }
        }

        $customersByTier = $this->buildTierBreakdown($customers, $totalCustomers);

        $topCustomers = collect($customerStats)
            ->map(function (array $stat, string $customerId) use ($customerMap) {
                $customer = $customerMap->get($customerId);
                if (! $customer) {
                    return null;
                }

                $fullName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
                $fullName = $fullName !== '' ? $fullName : ($customer->customer_code ?? 'Unknown Customer');

                $transactions = max($stat['totalPurchases'], 1);

                return [
                    'customerId' => $customerId,
                    'customerName' => $fullName,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'totalPurchases' => $stat['totalPurchases'],
                    'totalSpent' => round($stat['totalSpent'], 2),
                    'averageOrderValue' => round($stat['totalSpent'] / $transactions, 2),
                    'lastPurchaseDate' => $stat['lastPurchase']?->toISOString(),
                    'loyaltyPoints' => (int) ($customer->loyalty_points ?? 0),
                ];
            })
            ->filter()
            ->sortByDesc('totalSpent')
            ->values()
            ->take(10)
            ->all();

        $uniqueCustomers = count($customerStats);
        $repeatCustomers = collect($customerStats)
            ->filter(fn (array $stat) => $stat['totalPurchases'] > 1)
            ->count();

        $averageCustomerValue = $uniqueCustomers > 0
            ? round($totalRevenue / $uniqueCustomers, 2)
            : 0.0;

        $repeatCustomerRate = $uniqueCustomers > 0
            ? round(($repeatCustomers / $uniqueCustomers) * 100, 2)
            : 0.0;

        $newCustomerRate = $totalCustomers > 0
            ? round(($newCustomers / $totalCustomers) * 100, 2)
            : 0.0;

        $retentionRate = $totalCustomers > 0
            ? round(($activeCustomers / $totalCustomers) * 100, 2)
            : 0.0;

        $customerLifetimeValue = round(
            $averageCustomerValue * (1 + ($repeatCustomerRate / 100)),
            2
        );

        return [
            'id' => (string) Str::uuid(),
            'reportDate' => Carbon::now()->toISOString(),
            'totalCustomers' => $totalCustomers,
            'newCustomers' => $newCustomers,
            'activeCustomers' => $activeCustomers,
            'inactiveCustomers' => $inactiveCustomers,
            'topCustomers' => $topCustomers,
            'customersByTier' => $customersByTier,
            'customerRetention' => [
                'retentionRate' => $retentionRate,
                'churnRate' => max(0, round(100 - $retentionRate, 2)),
                'repeatCustomerRate' => $repeatCustomerRate,
                'newCustomerRate' => $newCustomerRate,
            ],
            'averageCustomerValue' => $averageCustomerValue,
            'customerLifetimeValue' => $customerLifetimeValue,
        ];
    }

    private function buildTierBreakdown(Collection $customers, int $totalCustomers): array
    {
        return $customers
            ->groupBy(function (Customer $customer) {
                return $customer->loyalty_tier ?: 'Unassigned';
            })
            ->map(function (Collection $group, string $tier) use ($totalCustomers) {
                $count = $group->count();
                $totalSpent = $group->sum(fn (Customer $customer) => (float) ($customer->total_spent ?? 0));

                return [
                    'tier' => $tier,
                    'customerCount' => $count,
                    'totalSpent' => round($totalSpent, 2),
                    'averageSpent' => $count > 0 ? round($totalSpent / $count, 2) : 0,
                    'percentage' => $totalCustomers > 0
                        ? round(($count / $totalCustomers) * 100, 2)
                        : 0,
                ];
            })
            ->sortByDesc('totalSpent')
            ->values()
            ->all();
    }

    private function resolveDateRange(array $filters): array
    {
        $period = $filters['period'] ?? 'this_month';
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [
                $now->copy()->subYear()->startOfYear(),
                $now->copy()->subYear()->endOfYear(),
            ],
            'custom' => $this->customRange($filters, $now),
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    private function customRange(array $filters, Carbon $fallbackNow): array
    {
        $start = ! empty($filters['startDate'])
            ? Carbon::parse($filters['startDate'])
            : $fallbackNow->copy()->subMonth();
        $end = ! empty($filters['endDate'])
            ? Carbon::parse($filters['endDate'])
            : $fallbackNow->copy();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy(), $start->copy()];
        }

        return [$start->startOfDay(), $end->endOfDay()];
    }

    private function emptyReport(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'reportDate' => Carbon::now()->toISOString(),
            'totalCustomers' => 0,
            'newCustomers' => 0,
            'activeCustomers' => 0,
            'inactiveCustomers' => 0,
            'topCustomers' => [],
            'customersByTier' => [],
            'customerRetention' => [
                'retentionRate' => 0,
                'churnRate' => 0,
                'repeatCustomerRate' => 0,
                'newCustomerRate' => 0,
            ],
            'averageCustomerValue' => 0,
            'customerLifetimeValue' => 0,
        ];
    }
}
