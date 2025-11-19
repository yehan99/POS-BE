<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesTransaction;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class ReportsDashboardService
{
    public function __construct(
        private readonly SalesTransaction $salesTransaction,
        private readonly Product $product,
        private readonly Customer $customer,
    ) {
    }

    public function summary(): array
    {
        $now = Carbon::now();

        $salesSummary = $this->buildSalesSummary($now);
        $inventorySummary = $this->buildInventorySummary($now);
        $customerSummary = $this->buildCustomerSummary($now);
        $productSummary = $this->buildProductSummary($now);

        return [
            'salesSummary' => $salesSummary,
            'inventorySummary' => $inventorySummary,
            'customerSummary' => $customerSummary,
            'productSummary' => $productSummary,
        ];
    }

    private function buildSalesSummary(Carbon $now): array
    {
        $todayRange = [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        $yesterdayRange = [
            $now->copy()->subDay()->startOfDay(),
            $now->copy()->subDay()->endOfDay(),
        ];
        $weekRange = [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
        $monthRange = [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        $yearRange = [$now->copy()->startOfYear(), $now->copy()->endOfYear()];

        $today = $this->sumTransactions(...$todayRange);
        $yesterday = $this->sumTransactions(...$yesterdayRange);
        $week = $this->sumTransactions(...$weekRange);
        $month = $this->sumTransactions(...$monthRange);
        $year = $this->sumTransactions(...$yearRange);

        $salesGrowth = $yesterday['amount'] > 0
            ? round((($today['amount'] - $yesterday['amount']) / $yesterday['amount']) * 100, 2)
            : 0.0;

        $transactionGrowth = $yesterday['count'] > 0
            ? round((($today['count'] - $yesterday['count']) / $yesterday['count']) * 100, 2)
            : 0.0;

        return [
            'todaySales' => round($today['amount'], 2),
            'yesterdaySales' => round($yesterday['amount'], 2),
            'weekSales' => round($week['amount'], 2),
            'monthSales' => round($month['amount'], 2),
            'yearSales' => round($year['amount'], 2),
            'salesGrowth' => $salesGrowth,
            'transactionGrowth' => $transactionGrowth,
        ];
    }

    private function sumTransactions(Carbon $start, Carbon $end): array
    {
        $query = $this->salesTransaction->newQuery()
            ->completed()
            ->whereBetween('transaction_date', [$start, $end]);

        return [
            'amount' => (float) $query->sum('total'),
            'count' => (int) $query->count(),
        ];
    }

    private function buildInventorySummary(Carbon $now): array
    {
        $products = $this->product->newQuery()
            ->select([
                'id',
                'stock_quantity',
                'cost_price',
                'reorder_level',
            ])
            ->get();

        $totalValue = $products->sum(function (Product $product) {
            return (int) ($product->stock_quantity ?? 0) * (float) ($product->cost_price ?? 0);
        });

        $lowStock = $products->filter(function (Product $product) {
            if ($product->reorder_level === null) {
                return false;
            }

            return (int) ($product->stock_quantity ?? 0) <= (int) $product->reorder_level;
        })->count();

        $outOfStock = $products->filter(fn (Product $product) => (int) ($product->stock_quantity ?? 0) <= 0)->count();

        $totalStockQuantity = (int) $products->sum(fn (Product $product) => (int) ($product->stock_quantity ?? 0));

        $last30Days = [$now->copy()->subDays(30), $now];
        $quantitySold = $this->sumQuantitySold(...$last30Days);

        $turnoverRate = $totalStockQuantity > 0
            ? round($quantitySold / max($totalStockQuantity, 1), 2)
            : 0.0;

        return [
            'totalValue' => round($totalValue, 2),
            'lowStockCount' => $lowStock,
            'outOfStockCount' => $outOfStock,
            'turnoverRate' => $turnoverRate,
        ];
    }

    private function sumQuantitySold(Carbon $start, Carbon $end): float
    {
        $transactions = $this->salesTransaction->newQuery()
            ->completed()
            ->whereBetween('transaction_date', [$start, $end])
            ->get(['items']);

        $quantity = 0.0;
        foreach ($transactions as $transaction) {
            $items = $transaction->items ?? [];
            foreach ($items as $item) {
                $quantity += (float) Arr::get($item, 'quantity', 0);
            }
        }

        return $quantity;
    }

    private function buildCustomerSummary(Carbon $now): array
    {
        $totalCustomers = (int) $this->customer->newQuery()->count();

        $recentThreshold = $now->copy()->subDays(90);

        $activeCustomers = (int) $this->customer->newQuery()
            ->where(function ($query) use ($recentThreshold) {
                $query->where('is_active', true)
                    ->orWhere('last_purchase_at', '>=', $recentThreshold);
            })
            ->count();

        $retentionRate = $totalCustomers > 0
            ? round(($activeCustomers / $totalCustomers) * 100, 2)
            : 0.0;

        $averageLifetimeValue = (float) $this->customer->newQuery()->avg('total_spent');

        return [
            'totalCustomers' => $totalCustomers,
            'activeCustomers' => $activeCustomers,
            'retentionRate' => $retentionRate,
            'averageLifetimeValue' => round($averageLifetimeValue, 2),
        ];
    }

    private function buildProductSummary(Carbon $now): array
    {
        $products = $this->product->newQuery()
            ->select(['id', 'stock_quantity', 'cost_price', 'price'])
            ->get()
            ->keyBy('id');

        $totalProducts = $products->count();

        $windowStart = $now->copy()->subDays(90);
        $transactions = $this->salesTransaction->newQuery()
            ->completed()
            ->whereBetween('transaction_date', [$windowStart, $now])
            ->get(['items']);

        $productStats = [];
        foreach ($transactions as $transaction) {
            $items = $transaction->items ?? [];
            foreach ($items as $item) {
                $productId = (string) Arr::get($item, 'product_id');
                if ($productId === '') {
                    continue;
                }

                $quantity = (float) Arr::get($item, 'quantity', 0);
                if ($quantity <= 0) {
                    continue;
                }

                $lineTotal = (float) (Arr::get($item, 'total') ?? (Arr::get($item, 'price', 0) * $quantity));
                $product = $products->get($productId);
                $unitCost = (float) (Arr::get($item, 'cost') ?? $product?->cost_price ?? 0);
                $lineCost = $unitCost * $quantity;

                if (! isset($productStats[$productId])) {
                    $productStats[$productId] = [
                        'revenue' => 0.0,
                        'cost' => 0.0,
                    ];
                }

                $productStats[$productId]['revenue'] += $lineTotal;
                $productStats[$productId]['cost'] += $lineCost;
            }
        }

        $topPerformers = collect($productStats)
            ->filter(fn (array $stat) => $stat['revenue'] > 0 && ($stat['revenue'] - $stat['cost']) > 0)
            ->count();

        $underperformers = max($totalProducts - $topPerformers, 0);

        $totals = collect($productStats)->reduce(function (array $carry, array $stat) {
            $carry['revenue'] += $stat['revenue'];
            $carry['cost'] += $stat['cost'];

            return $carry;
        }, ['revenue' => 0.0, 'cost' => 0.0]);

        $averageMargin = $totals['revenue'] > 0
            ? round((($totals['revenue'] - $totals['cost']) / $totals['revenue']) * 100, 2)
            : 0.0;

        return [
            'totalProducts' => $totalProducts,
            'topPerformers' => $topPerformers,
            'underperformers' => $underperformers,
            'averageMargin' => $averageMargin,
        ];
    }
}
