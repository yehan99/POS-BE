<?php

namespace App\Services\Reports;

use App\Models\Product;
use App\Models\SalesTransaction;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductPerformanceReportService
{
    public function __construct(
        private readonly Product $product,
        private readonly SalesTransaction $salesTransaction,
    ) {
    }

    public function generate(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $productsQuery = $this->product->newQuery()->with('category');

        if (! empty($filters['productId'])) {
            $productsQuery->where('id', $filters['productId']);
        }

        if (! empty($filters['categoryId'])) {
            $productsQuery->where('category_id', $filters['categoryId']);
        }

        if (! empty($filters['status'])) {
            $status = strtolower((string) $filters['status']);
            if ($status === 'active') {
                $productsQuery->where('is_active', true);
            } elseif ($status === 'inactive') {
                $productsQuery->where('is_active', false);
            }
        }

        $products = $productsQuery->get();
        if ($products->isEmpty()) {
            return $this->emptyReport();
        }

        $productIds = $products->pluck('id')->filter()->values()->all();
        $productMap = $products->keyBy('id');

        $transactionQuery = $this->salesTransaction->newQuery()
            ->completed()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->whereNotNull('items');

        if (! empty($productIds)) {
            $transactionQuery->where(function ($query) use ($productIds) {
                foreach ($productIds as $productId) {
                    $query->orWhereJsonContains('items', ['product_id' => $productId]);
                }
            });
        }

        $transactions = $transactionQuery->get(['items', 'transaction_date']);

        [$productStats, $categoryStats] = $this->aggregateStats($transactions, $productIds, $productMap);

        $topPerformers = $this->formatProductCollection($productStats, $productMap)
            ->sortByDesc('revenue')
            ->values()
            ->take(15)
            ->all();

        $underperformers = $this->formatProductCollection($productStats, $productMap)
            ->sortBy('revenue')
            ->values()
            ->take(15)
            ->all();

        $profitableProducts = $this->formatProductCollection($productStats, $productMap)
            ->filter(fn (array $product) => $product['profit'] > 0)
            ->sortByDesc('profit')
            ->values()
            ->take(15)
            ->all();

        $revenueByCategory = $this->formatCategoryStats($categoryStats);
        $productTrends = $this->buildProductTrends($productStats, $productMap);

        $totalProducts = $products->count();
        $activeProducts = $products->filter(fn (Product $product) => (bool) $product->is_active)->count();

        return [
            'id' => (string) Str::uuid(),
            'reportDate' => Carbon::now()->toISOString(),
            'totalProducts' => $totalProducts,
            'activeProducts' => $activeProducts,
            'topPerformers' => $topPerformers,
            'underperformers' => $underperformers,
            'profitableProducts' => $profitableProducts,
            'revenueByCategory' => $revenueByCategory,
            'productTrends' => $productTrends,
        ];
    }

    private function aggregateStats(Collection $transactions, array $productIds, Collection $productMap): array
    {
        $productStats = [];
        $categoryStats = [];

        foreach ($transactions as $transaction) {
            $items = collect($transaction->items ?? []);
            if ($items->isEmpty()) {
                continue;
            }

            foreach ($items as $item) {
                $productId = (string) Arr::get($item, 'product_id');
                if ($productId === '') {
                    continue;
                }

                if (! empty($productIds) && ! in_array($productId, $productIds, true)) {
                    continue;
                }

                $quantity = (float) Arr::get($item, 'quantity', 0);
                if ($quantity <= 0) {
                    continue;
                }

                $lineTotal = (float) (Arr::get($item, 'total') ?? (Arr::get($item, 'price', 0) * $quantity));
                if ($lineTotal <= 0) {
                    continue;
                }

                $product = $productMap->get($productId);
                $unitCost = (float) (Arr::get($item, 'cost') ?? $product?->cost_price ?? 0);
                $lineCost = $unitCost * $quantity;

                if (! isset($productStats[$productId])) {
                    $productStats[$productId] = [
                        'productId' => $productId,
                        'quantity' => 0.0,
                        'revenue' => 0.0,
                        'cost' => 0.0,
                        'categoryId' => $product?->category_id,
                        'categoryName' => $product?->category?->name ?? Arr::get($item, 'category_name', 'Uncategorized'),
                        'stockLevel' => (int) ($product->stock_quantity ?? 0),
                        'weekly' => [],
                    ];
                }

                $productStats[$productId]['quantity'] += $quantity;
                $productStats[$productId]['revenue'] += $lineTotal;
                $productStats[$productId]['cost'] += $lineCost;

                $weekKey = $transaction->transaction_date
                    ? $transaction->transaction_date->copy()->startOfWeek()->format('Y-m-d')
                    : Carbon::now()->startOfWeek()->format('Y-m-d');

                if (! isset($productStats[$productId]['weekly'][$weekKey])) {
                    $productStats[$productId]['weekly'][$weekKey] = [
                        'sales' => 0.0,
                        'quantity' => 0.0,
                    ];
                }

                $productStats[$productId]['weekly'][$weekKey]['sales'] += $lineTotal;
                $productStats[$productId]['weekly'][$weekKey]['quantity'] += $quantity;

                $categoryKey = (string) ($product?->category_id ?? 'uncategorized');
                if (! isset($categoryStats[$categoryKey])) {
                    $categoryStats[$categoryKey] = [
                        'categoryId' => $product?->category_id,
                        'categoryName' => $product?->category?->name ?? Arr::get($item, 'category_name', 'Uncategorized'),
                        'revenue' => 0.0,
                        'profit' => 0.0,
                        'productIds' => [],
                    ];
                }

                $categoryStats[$categoryKey]['revenue'] += $lineTotal;
                $categoryStats[$categoryKey]['profit'] += ($lineTotal - $lineCost);
                $categoryStats[$categoryKey]['productIds'][$productId] = true;
            }
        }

        return [$productStats, $categoryStats];
    }

    private function formatProductCollection(array $stats, Collection $productMap): Collection
    {
        return collect($stats)->map(function (array $stat) use ($productMap) {
            $product = $productMap->get($stat['productId']);

            $profit = $stat['revenue'] - $stat['cost'];
            $margin = $stat['revenue'] > 0 ? round(($profit / $stat['revenue']) * 100, 2) : 0;
            $score = $this->calculatePerformanceScore($stat['quantity'], $stat['revenue'], $margin);

            $name = $product?->name ?? 'Unknown Product';
            $categoryName = $stat['categoryName'] ?? $product?->category?->name ?? 'Uncategorized';

            return [
                'productId' => $stat['productId'],
                'productName' => $name,
                'sku' => $product?->sku ?? 'N/A',
                'category' => $categoryName,
                'quantitySold' => (int) round($stat['quantity']),
                'revenue' => round($stat['revenue'], 2),
                'cost' => round($stat['cost'], 2),
                'profit' => round($profit, 2),
                'profitMargin' => $margin,
                'returnRate' => 0,
                'averageRating' => null,
                'stockLevel' => $stat['stockLevel'] ?? (int) ($product->stock_quantity ?? 0),
                'performanceScore' => $score,
            ];
        });
    }

    private function formatCategoryStats(array $categoryStats): array
    {
        return collect($categoryStats)
            ->map(function (array $stat) {
                $productCount = count($stat['productIds']);

                return [
                    'categoryId' => $stat['categoryId'],
                    'categoryName' => $stat['categoryName'],
                    'revenue' => round($stat['revenue'], 2),
                    'profit' => round($stat['profit'], 2),
                    'productCount' => $productCount,
                    'growthRate' => 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    private function buildProductTrends(array $stats, Collection $productMap): array
    {
        return collect($stats)
            ->sortByDesc(fn (array $stat) => $stat['revenue'])
            ->take(5)
            ->map(function (array $stat) use ($productMap) {
                $product = $productMap->get($stat['productId']);

                $weeklyData = collect($stat['weekly'])
                    ->sortKeys()
                    ->map(function (array $entry, string $weekStart) {
                        return [
                            'date' => Carbon::parse($weekStart)->toISOString(),
                            'sales' => round($entry['sales'], 2),
                            'quantity' => (int) round($entry['quantity']),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'productId' => $stat['productId'],
                    'productName' => $product?->name ?? 'Unknown Product',
                    'weeklyData' => $weeklyData,
                ];
            })
            ->values()
            ->all();
    }

    private function calculatePerformanceScore(float $quantity, float $revenue, float $margin): int
    {
        $quantityScore = min($quantity / 100, 1);
        $revenueScore = min($revenue / 100000, 1);
        $marginScore = min(max($margin / 100, 0), 1);

        return (int) round(($quantityScore * 0.3 + $revenueScore * 0.5 + $marginScore * 0.2) * 100);
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
            'totalProducts' => 0,
            'activeProducts' => 0,
            'topPerformers' => [],
            'underperformers' => [],
            'profitableProducts' => [],
            'revenueByCategory' => [],
            'productTrends' => [],
        ];
    }
}
