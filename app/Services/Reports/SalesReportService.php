<?php

namespace App\Services\Reports;

use App\Models\Product;
use App\Models\SalesTransaction;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SalesReportService
{
    public function __construct(private readonly SalesTransaction $salesTransaction)
    {
    }

    public function generate(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $query = $this->salesTransaction->newQuery()
            ->completed()
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if (!empty($filters['paymentMethod'])) {
            $query->where('payment_method', $filters['paymentMethod']);
        }

        if (!empty($filters['customerId'])) {
            $query->where('customer_id', $filters['customerId']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $transactions = $query->orderBy('transaction_date')->get();

        if ($transactions->isEmpty()) {
            return $this->emptyReport();
        }

        $productIds = $transactions
            ->flatMap(fn ($transaction) => collect($transaction->items ?? [])->pluck('product_id'))
            ->filter()
            ->unique()
            ->all();

        /** @var Collection<string, Product> $products */
        $products = Product::with('category')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $categoryFilter = $filters['categoryId'] ?? null;

        $totalSales = 0.0;
        $totalDiscount = 0.0;
        $totalTax = 0.0;
        $totalItems = 0.0;
        $grossProfit = 0.0;
        $transactionCount = 0;

        $productStats = [];
        $categoryStats = [];
        $paymentStats = [];
        $dailyStats = [];

        foreach ($transactions as $transaction) {
            $items = collect($transaction->items ?? []);
            if ($items->isEmpty()) {
                continue;
            }

            $transactionItemSales = 0.0;
            $transactionItemQuantity = 0.0;

            foreach ($items as $item) {
                $productId = Arr::get($item, 'product_id');
                $product = $productId ? $products->get($productId) : null;
                $categoryId = $product?->category_id ?? Arr::get($item, 'category_id');

                if ($categoryFilter && (string) $categoryId !== (string) $categoryFilter) {
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

                $transactionItemSales += $lineTotal;
                $transactionItemQuantity += $quantity;

                $totalItems += $quantity;

                $productKey = $productId ?? Arr::get($item, 'sku', Str::uuid()->toString());
                if (!isset($productStats[$productKey])) {
                    $productStats[$productKey] = [
                        'productId' => (string) $productId,
                        'productName' => Arr::get($item, 'name', 'Unknown Product'),
                        'sku' => Arr::get($item, 'sku', 'N/A'),
                        'quantitySold' => 0.0,
                        'revenue' => 0.0,
                        'cost' => 0.0,
                    ];
                }

                $productStats[$productKey]['quantitySold'] += $quantity;
                $productStats[$productKey]['revenue'] += $lineTotal;

                $unitCost = (float) (Arr::get($item, 'cost') ?? $product?->cost_price ?? 0);
                $productStats[$productKey]['cost'] += $unitCost * $quantity;

                $categoryKey = $categoryId ?? 'uncategorized';
                if (!isset($categoryStats[$categoryKey])) {
                    $categoryStats[$categoryKey] = [
                        'categoryId' => (string) $categoryId,
                        'categoryName' => $product?->category?->name ?? Arr::get($item, 'category_name', 'Uncategorized'),
                        'totalSales' => 0.0,
                        'totalQuantity' => 0.0,
                    ];
                }

                $categoryStats[$categoryKey]['totalSales'] += $lineTotal;
                $categoryStats[$categoryKey]['totalQuantity'] += $quantity;
            }

            if ($transactionItemSales <= 0) {
                continue;
            }

            $transactionSubtotal = (float) ($transaction->subtotal ?? $transactionItemSales);
            $ratio = $transactionSubtotal > 0 ? min(1, $transactionItemSales / $transactionSubtotal) : 1;

            $totalSales += (float) $transaction->total * $ratio;
            $totalDiscount += (float) $transaction->discount_amount * $ratio;
            $totalTax += (float) $transaction->tax_amount * $ratio;
            $transactionCount++;

            $methodKey = strtolower($transaction->payment_method ?? 'unknown');
            if (!isset($paymentStats[$methodKey])) {
                $paymentStats[$methodKey] = [
                    'paymentMethod' => Str::title(str_replace('_', ' ', $methodKey)),
                    'amount' => 0.0,
                    'transactionCount' => 0,
                ];
            }

            $paymentStats[$methodKey]['amount'] += (float) $transaction->total * $ratio;
            $paymentStats[$methodKey]['transactionCount']++;

            $dateKey = $transaction->transaction_date->copy()->startOfDay()->format('Y-m-d');
            if (!isset($dailyStats[$dateKey])) {
                $dailyStats[$dateKey] = [
                    'date' => $dateKey,
                    'sales' => 0.0,
                    'transactions' => 0,
                ];
            }

            $dailyStats[$dateKey]['sales'] += (float) $transaction->total * $ratio;
            $dailyStats[$dateKey]['transactions']++;
        }

        if (empty($productStats)) {
            return $this->emptyReport();
        }

        $topProducts = collect($productStats)
            ->map(function (array $stat) use (&$grossProfit) {
                $profit = $stat['revenue'] - $stat['cost'];
                $grossProfit += $profit;

                return [
                    'productId' => $stat['productId'],
                    'productName' => $stat['productName'],
                    'sku' => $stat['sku'],
                    'quantitySold' => (int) round($stat['quantitySold']),
                    'revenue' => round($stat['revenue'], 2),
                    'profit' => round($profit, 2),
                    'profitMargin' => $stat['revenue'] > 0
                        ? round(($profit / $stat['revenue']) * 100, 2)
                        : 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();

        $categoryTotals = collect($categoryStats)
            ->map(function (array $stat) {
                return [
                    'categoryId' => $stat['categoryId'],
                    'categoryName' => $stat['categoryName'],
                    'totalSales' => round($stat['totalSales'], 2),
                    'totalQuantity' => (int) round($stat['totalQuantity']),
                ];
            });

        $totalCategorySales = $categoryTotals->sum('totalSales');
        $formattedCategories = $categoryTotals
            ->map(function (array $stat) use ($totalCategorySales) {
                $percentage = $totalCategorySales > 0
                    ? round(($stat['totalSales'] / $totalCategorySales) * 100, 2)
                    : 0;

                return array_merge($stat, ['percentage' => $percentage]);
            })
            ->sortByDesc('totalSales')
            ->values()
            ->all();

        $paymentTotals = collect($paymentStats)
            ->map(function (array $stat) {
                return [
                    'paymentMethod' => $stat['paymentMethod'],
                    'amount' => round($stat['amount'], 2),
                    'transactionCount' => $stat['transactionCount'],
                ];
            });

        $totalPaymentAmount = $paymentTotals->sum('amount');
        $formattedPayments = $paymentTotals
            ->map(function (array $stat) use ($totalPaymentAmount) {
                $percentage = $totalPaymentAmount > 0
                    ? round(($stat['amount'] / $totalPaymentAmount) * 100, 2)
                    : 0;

                return array_merge($stat, ['percentage' => $percentage]);
            })
            ->sortByDesc('amount')
            ->values()
            ->all();

        $dailyBreakdown = collect($dailyStats)
            ->sortKeys()
            ->map(function (array $stat) {
                $average = $stat['transactions'] > 0
                    ? $stat['sales'] / $stat['transactions']
                    : 0;

                return [
                    'date' => $stat['date'],
                    'sales' => round($stat['sales'], 2),
                    'transactions' => $stat['transactions'],
                    'averageTransactionValue' => round($average, 2),
                ];
            })
            ->values()
            ->all();

        $netSales = max($totalSales - $totalTax, 0);

        return [
            'id' => (string) Str::uuid(),
            'reportDate' => Carbon::now()->toISOString(),
            'totalSales' => round($totalSales, 2),
            'totalTransactions' => $transactionCount,
            'averageTransactionValue' => $transactionCount > 0
                ? round($totalSales / $transactionCount, 2)
                : 0,
            'totalItems' => (int) round($totalItems),
            'totalDiscount' => round($totalDiscount, 2),
            'totalTax' => round($totalTax, 2),
            'netSales' => round($netSales, 2),
            'grossProfit' => round($grossProfit, 2),
            'grossProfitMargin' => $netSales > 0
                ? round(($grossProfit / $netSales) * 100, 2)
                : 0,
            'topSellingProducts' => array_slice($topProducts, 0, 10),
            'salesByCategory' => $formattedCategories,
            'salesByPaymentMethod' => $formattedPayments,
            'dailyBreakdown' => $dailyBreakdown,
        ];
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
        $start = !empty($filters['startDate'])
            ? Carbon::parse($filters['startDate'])
            : $fallbackNow->copy()->subMonth();
        $end = !empty($filters['endDate'])
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
            'totalSales' => 0,
            'totalTransactions' => 0,
            'averageTransactionValue' => 0,
            'totalItems' => 0,
            'totalDiscount' => 0,
            'totalTax' => 0,
            'netSales' => 0,
            'grossProfit' => 0,
            'grossProfitMargin' => 0,
            'topSellingProducts' => [],
            'salesByCategory' => [],
            'salesByPaymentMethod' => [],
            'dailyBreakdown' => [],
        ];
    }
}
