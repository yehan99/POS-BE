<?php

namespace App\Services\Reports;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\SalesTransaction;
use App\Models\StockAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class InventoryReportService
{
    public function __construct(
        private readonly Product $product,
        private readonly PurchaseOrderItem $purchaseOrderItem,
        private readonly SalesTransaction $salesTransaction,
        private readonly StockAdjustment $stockAdjustment,
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

        $products = $productsQuery->orderBy('name')->get();

        if ($products->isEmpty()) {
            return $this->emptyReport();
        }

        $productIds = $products->pluck('id')->filter()->values()->all();
        $productMap = $products->keyBy('id');

        $stockIn = $this->fetchStockIn($productIds, $startDate, $endDate);
        $stockOut = $this->fetchStockOut($productIds, $startDate, $endDate);
        $adjustments = $this->fetchAdjustments($productIds, $startDate, $endDate, $filters);

        $now = Carbon::now();

        $totalStockQuantity = (int) round($products->sum(fn (Product $product) => (int) ($product->stock_quantity ?? 0)));
        $totalStockValue = round($products->sum(function (Product $product) {
            $quantity = (int) ($product->stock_quantity ?? 0);
            $cost = (float) ($product->cost_price ?? 0);

            return $quantity * $cost;
        }), 2);

        $lowStockItems = $products->filter(function (Product $product) {
            if ($product->reorder_level === null) {
                return false;
            }

            return (int) ($product->stock_quantity ?? 0) <= (int) $product->reorder_level;
        })->count();

        $outOfStockItems = $products->filter(fn (Product $product) => (int) ($product->stock_quantity ?? 0) <= 0)->count();

        $overstockItems = $products->filter(function (Product $product) {
            if ($product->max_stock_level === null) {
                return false;
            }

            return (int) ($product->stock_quantity ?? 0) > (int) $product->max_stock_level;
        })->count();

        $stockMovement = $products->map(function (Product $product) use ($stockIn, $stockOut, $adjustments) {
            $productId = (string) $product->id;
            $currentStock = (int) ($product->stock_quantity ?? 0);
            $incoming = $stockIn->get($productId, ['quantity' => 0]);
            $outgoing = $stockOut->get($productId, ['quantity' => 0]);
            $inQuantity = (float) ($incoming['quantity'] ?? 0);
            $outQuantity = (float) ($outgoing['quantity'] ?? 0);
            $adjustmentQuantity = (float) $adjustments->get($productId, 0);
            $openingStock = max($currentStock - $inQuantity + $outQuantity - $adjustmentQuantity, 0);
            $movementBase = max($openingStock ?: $currentStock, 1);

            return [
                'productId' => $productId,
                'productName' => $product->name,
                'sku' => $product->sku ?? 'N/A',
                'openingStock' => (int) round($openingStock),
                'stockIn' => (int) round($inQuantity),
                'stockOut' => (int) round($outQuantity),
                'adjustments' => (int) round($adjustmentQuantity),
                'closingStock' => $currentStock,
                'movementRate' => ($inQuantity + $outQuantity) > 0
                    ? round(($inQuantity + $outQuantity) / $movementBase, 2)
                    : 0,
            ];
        })->values()->all();

        $stockValuation = $products->map(function (Product $product) {
            $quantity = (int) ($product->stock_quantity ?? 0);
            $costPrice = (float) ($product->cost_price ?? 0);
            $sellingPrice = (float) ($product->price ?? 0);
            $totalCost = $quantity * $costPrice;
            $totalValue = $quantity * $sellingPrice;

            return [
                'productId' => (string) $product->id,
                'productName' => $product->name,
                'sku' => $product->sku ?? 'N/A',
                'quantity' => $quantity,
                'costPrice' => round($costPrice, 2),
                'sellingPrice' => round($sellingPrice, 2),
                'totalCost' => round($totalCost, 2),
                'totalValue' => round($totalValue, 2),
                'potentialProfit' => round($totalValue - $totalCost, 2),
            ];
        })->values()->all();

        $agingAnalysis = $this->buildAgingAnalysis($products, $now);

        $topMovingProducts = $stockOut
            ->map(function (array $stat, $productId) use ($productMap) {
                $product = $productMap->get($productId);
                if (! $product) {
                    return null;
                }

                $currentStock = max((int) ($product->stock_quantity ?? 0), 0);
                $turnoverBase = $currentStock > 0 ? $currentStock : 1;

                return [
                    'productId' => (string) $product->id,
                    'productName' => $product->name,
                    'sku' => $product->sku ?? 'N/A',
                    'turnoverRate' => round(($stat['quantity'] ?? 0) / $turnoverBase, 2),
                    'quantityMoved' => (int) round($stat['quantity'] ?? 0),
                    'revenue' => round($stat['revenue'] ?? 0, 2),
                ];
            })
            ->filter()
            ->sortByDesc('quantityMoved')
            ->values()
            ->take(10)
            ->all();

        $slowMovingProducts = $products
            ->map(function (Product $product) use ($stockOut, $now) {
                $productId = (string) $product->id;
                $stat = $stockOut->get($productId, [
                    'quantity' => 0,
                    'lastSaleDate' => null,
                ]);

                $lastSaleDate = $stat['lastSaleDate'] ?? null;

                return [
                    'productId' => $productId,
                    'productName' => $product->name,
                    'sku' => $product->sku ?? 'N/A',
                    'daysInStock' => $product->created_at
                        ? $product->created_at->diffInDays($now)
                        : 0,
                    'currentStock' => (int) ($product->stock_quantity ?? 0),
                    'lastSaleDate' => $lastSaleDate instanceof Carbon
                        ? $lastSaleDate->toISOString()
                        : null,
                    'stockValue' => round((int) ($product->stock_quantity ?? 0) * (float) ($product->cost_price ?? 0), 2),
                    'quantityMoved' => (int) round($stat['quantity'] ?? 0),
                ];
            })
            ->sort(function (array $a, array $b) {
                $quantityCompare = $a['quantityMoved'] <=> $b['quantityMoved'];
                if ($quantityCompare !== 0) {
                    return $quantityCompare;
                }

                return $b['currentStock'] <=> $a['currentStock'];
            })
            ->map(function (array $item) {
                unset($item['quantityMoved']);

                return $item;
            })
            ->values()
            ->take(10)
            ->all();

        return [
            'id' => (string) Str::uuid(),
            'reportDate' => Carbon::now()->toISOString(),
            'totalProducts' => $products->count(),
            'totalStockValue' => round($totalStockValue, 2),
            'totalStockQuantity' => $totalStockQuantity,
            'lowStockItems' => $lowStockItems,
            'outOfStockItems' => $outOfStockItems,
            'overstockItems' => $overstockItems,
            'stockMovement' => $stockMovement,
            'stockValuation' => $stockValuation,
            'agingAnalysis' => $agingAnalysis,
            'topMovingProducts' => $topMovingProducts,
            'slowMovingProducts' => $slowMovingProducts,
        ];
    }

    private function fetchStockIn(array $productIds, Carbon $startDate, Carbon $endDate): Collection
    {
        $query = $this->purchaseOrderItem->newQuery()
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (! empty($productIds)) {
            $query->whereIn('product_id', $productIds);
        }

        $items = $query->get(['product_id', 'quantity', 'received_quantity', 'total', 'unit_cost']);

        return $items->groupBy('product_id')->map(function (Collection $group) {
            $quantity = $group->sum(function ($item) {
                $received = $item->received_quantity ?? null;
                $baseQuantity = $received !== null && $received > 0 ? $received : $item->quantity;

                return (float) $baseQuantity;
            });

            $value = $group->sum(function ($item) {
                $lineTotal = $item->total ?? null;
                if ($lineTotal !== null) {
                    return (float) $lineTotal;
                }

                $quantity = $item->received_quantity ?? $item->quantity ?? 0;

                return (float) ($item->unit_cost ?? 0) * (float) $quantity;
            });

            return [
                'quantity' => $quantity,
                'value' => $value,
            ];
        });
    }

    private function fetchStockOut(array $productIds, Carbon $startDate, Carbon $endDate): Collection
    {
        $query = $this->salesTransaction->newQuery()
            ->completed()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date');

        $transactions = $query->get(['items', 'transaction_date']);
        $stats = [];

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

                if (! isset($stats[$productId])) {
                    $stats[$productId] = [
                        'quantity' => 0.0,
                        'revenue' => 0.0,
                        'lastSaleDate' => null,
                    ];
                }

                $stats[$productId]['quantity'] += $quantity;
                $stats[$productId]['revenue'] += $lineTotal;

                $currentLastSale = $stats[$productId]['lastSaleDate'];
                if ($currentLastSale === null || $transaction->transaction_date->gt($currentLastSale)) {
                    $stats[$productId]['lastSaleDate'] = $transaction->transaction_date->copy();
                }
            }
        }

        return collect($stats);
    }

    private function fetchAdjustments(array $productIds, Carbon $startDate, Carbon $endDate, array $filters): Collection
    {
        $query = $this->stockAdjustment->newQuery()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', StockAdjustment::STATUS_APPROVED);

        if (! empty($productIds)) {
            $query->whereIn('product_id', $productIds);
        }

        if (! empty($filters['locationId'])) {
            $query->where('location_id', $filters['locationId']);
        }

        $adjustments = $query->get(['product_id', 'quantity', 'quantity_change']);

        return $adjustments->groupBy('product_id')->map(function (Collection $group) {
            return $group->sum(function ($adjustment) {
                $quantityChange = $adjustment->quantity_change ?? null;
                if ($quantityChange !== null && $quantityChange !== 0) {
                    return (float) $quantityChange;
                }

                return (float) ($adjustment->quantity ?? 0);
            });
        });
    }

    private function buildAgingAnalysis(Collection $products, Carbon $now): array
    {
        $buckets = collect([
            '0-30' => [
                'ageRange' => '0-30',
                'productCount' => 0,
                'totalValue' => 0.0,
            ],
            '31-60' => [
                'ageRange' => '31-60',
                'productCount' => 0,
                'totalValue' => 0.0,
            ],
            '61-90' => [
                'ageRange' => '61-90',
                'productCount' => 0,
                'totalValue' => 0.0,
            ],
            '90+' => [
                'ageRange' => '90+',
                'productCount' => 0,
                'totalValue' => 0.0,
            ],
        ]);

        foreach ($products as $product) {
            $referenceDate = $product->updated_at ?? $product->created_at ?? $now;
            $age = $referenceDate instanceof Carbon ? $referenceDate->diffInDays($now) : 0;

            $bucketKey = match (true) {
                $age <= 30 => '0-30',
                $age <= 60 => '31-60',
                $age <= 90 => '61-90',
                default => '90+',
            };

            $bucket = $buckets->get($bucketKey);
            $bucket['productCount']++;
            $bucket['totalValue'] += (int) ($product->stock_quantity ?? 0) * (float) ($product->cost_price ?? 0);
            $buckets->put($bucketKey, $bucket);
        }

        $totalValue = $buckets->sum('totalValue');

        return $buckets->map(function (array $bucket) use ($totalValue) {
            $bucket['totalValue'] = round($bucket['totalValue'], 2);
            $bucket['percentage'] = $totalValue > 0
                ? round(($bucket['totalValue'] / $totalValue) * 100, 2)
                : 0;

            return $bucket;
        })->values()->all();
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
            'totalStockValue' => 0,
            'totalStockQuantity' => 0,
            'lowStockItems' => 0,
            'outOfStockItems' => 0,
            'overstockItems' => 0,
            'stockMovement' => [],
            'stockValuation' => [],
            'agingAnalysis' => [
                ['ageRange' => '0-30', 'productCount' => 0, 'totalValue' => 0, 'percentage' => 0],
                ['ageRange' => '31-60', 'productCount' => 0, 'totalValue' => 0, 'percentage' => 0],
                ['ageRange' => '61-90', 'productCount' => 0, 'totalValue' => 0, 'percentage' => 0],
                ['ageRange' => '90+', 'productCount' => 0, 'totalValue' => 0, 'percentage' => 0],
            ],
            'topMovingProducts' => [],
            'slowMovingProducts' => [],
        ];
    }
}
