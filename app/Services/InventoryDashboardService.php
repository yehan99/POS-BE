<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

class InventoryDashboardService
{
    /**
     * Get dashboard metrics (snapshot tiles)
     */
    public function getMetrics(): array
    {
        $totalProducts = Product::query()
            ->where('track_inventory', true)
            ->count();

        $totalValue = Product::query()
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->selectRaw('SUM(stock_quantity * cost_price) as total')
            ->value('total') ?? 0;

        $lowStockCount = Product::query()
            ->where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'reorder_level')
            ->where('stock_quantity', '>', 0)
            ->count();

        $outOfStockCount = Product::query()
            ->where('track_inventory', true)
            ->where('stock_quantity', '<=', 0)
            ->count();

        $activeLocations = DB::table('inventory_locations')
            ->where('is_active', true)
            ->count();

        $pendingPOs = PurchaseOrder::query()
            ->whereIn('status', [
                'draft',
                'pending',
                'approved',
                'ordered',
            ])
            ->count();

        return [
            [
                'label' => 'Total Products',
                'value' => $totalProducts,
                'icon' => 'inventory_2',
                'colorClass' => 'primary',
                'route' => '/inventory/products',
            ],
            [
                'label' => 'Inventory Value',
                'value' => '$' . number_format($totalValue, 2),
                'icon' => 'payments',
                'colorClass' => 'accent',
                'route' => null,
            ],
            [
                'label' => 'Low Stock Items',
                'value' => $lowStockCount,
                'icon' => 'warning',
                'colorClass' => 'warn',
                'route' => '/inventory/stock-alerts',
            ],
            [
                'label' => 'Out of Stock',
                'value' => $outOfStockCount,
                'icon' => 'error',
                'colorClass' => 'error',
                'route' => '/inventory/stock-alerts',
            ],
            [
                'label' => 'Active Locations',
                'value' => $activeLocations,
                'icon' => 'store',
                'colorClass' => 'info',
                'route' => '/inventory/locations',
            ],
            [
                'label' => 'Pending POs',
                'value' => $pendingPOs,
                'icon' => 'receipt_long',
                'colorClass' => 'accent',
                'route' => '/inventory/purchase-orders',
            ],
        ];
    }

    /**
     * Get pipeline items (operations in progress)
     */
    public function getPipelineItems(): array
    {
        $pendingAdjustments = StockAdjustment::query()
            ->where('status', StockAdjustment::STATUS_PENDING)
            ->count();

        $pendingTransfers = StockTransfer::query()
            ->whereIn('status', [
                StockTransfer::STATUS_PENDING,
                StockTransfer::STATUS_APPROVED,
                StockTransfer::STATUS_IN_TRANSIT,
            ])
            ->count();

        $activePOs = PurchaseOrder::query()
            ->whereIn('status', [
                'approved',
                'ordered',
            ])
            ->count();

        return [
            [
                'icon' => 'edit_note',
                'iconClass' => 'edit',
                'label' => 'Pending Adjustments',
                'hint' => 'Stock adjustments awaiting approval',
                'value' => $pendingAdjustments,
                'route' => '/inventory/stock-adjustments',
            ],
            [
                'icon' => 'sync_alt',
                'iconClass' => 'transfer',
                'label' => 'Active Transfers',
                'hint' => 'Stock transfers in progress',
                'value' => $pendingTransfers,
                'route' => '/inventory/stock-transfers',
            ],
            [
                'icon' => 'shopping_cart',
                'iconClass' => 'po',
                'label' => 'Active Purchase Orders',
                'hint' => 'Purchase orders awaiting receipt',
                'value' => $activePOs,
                'route' => '/inventory/purchase-orders',
            ],
        ];
    }

    /**
     * Get exception counts
     */
    public function getExceptions(): array
    {
        $criticalStock = Product::query()
            ->where('track_inventory', true)
            ->where('stock_quantity', '<=', 0)
            ->count();

        $lowStock = Product::query()
            ->where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'reorder_level')
            ->where('stock_quantity', '>', 0)
            ->count();

        $overstock = Product::query()
            ->where('track_inventory', true)
            ->whereColumn('stock_quantity', '>', 'max_stock_level')
            ->whereNotNull('max_stock_level')
            ->where('max_stock_level', '>', 0)
            ->count();

        $expiringSoon = 0; // Placeholder - implement if you track expiry dates

        return [
            [
                'icon' => 'error',
                'severity' => 'critical',
                'label' => 'Out of Stock',
                'value' => $criticalStock . ' items',
                'route' => '/inventory/stock-alerts',
            ],
            [
                'icon' => 'warning',
                'severity' => 'warning',
                'label' => 'Low Stock',
                'value' => $lowStock . ' items',
                'route' => '/inventory/stock-alerts',
            ],
            [
                'icon' => 'inventory',
                'severity' => 'info',
                'label' => 'Overstock',
                'value' => $overstock . ' items',
                'route' => '/inventory/stock-alerts',
            ],
            [
                'icon' => 'schedule',
                'severity' => 'warning',
                'label' => 'Expiring Soon',
                'value' => $expiringSoon . ' items',
                'route' => '/inventory/stock-alerts',
            ],
        ];
    }

    /**
     * Get stock alerts
     */
    public function getAlerts(int $limit = 5): array
    {
        $alerts = [];

        // Get critical out-of-stock items
        $outOfStock = Product::query()
            ->where('track_inventory', true)
            ->where('stock_quantity', '<=', 0)
            ->where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($outOfStock as $product) {
            $alerts[] = [
                'id' => $product->id,
                'type' => 'out_of_stock',
                'severity' => 'critical',
                'title' => $product->name,
                'message' => 'Product is out of stock and needs immediate attention',
                'stock' => [
                    'current' => 0,
                    'reorder' => $product->reorder_level ?? 0,
                ],
                'timestamp' => $product->updated_at?->toIso8601String(),
            ];
        }

        // Get low stock items if we need more alerts
        if (count($alerts) < $limit) {
            $remaining = $limit - count($alerts);
            $lowStock = Product::query()
                ->where('track_inventory', true)
                ->whereColumn('stock_quantity', '<=', 'reorder_level')
                ->where('stock_quantity', '>', 0)
                ->where('is_active', true)
                ->orderBy('stock_quantity', 'asc')
                ->limit($remaining)
                ->get();

            foreach ($lowStock as $product) {
                $alerts[] = [
                    'id' => $product->id,
                    'type' => 'low_stock',
                    'severity' => 'warning',
                    'title' => $product->name,
                    'message' => 'Stock level is below reorder point',
                    'stock' => [
                        'current' => $product->stock_quantity ?? 0,
                        'reorder' => $product->reorder_level ?? 0,
                    ],
                    'timestamp' => $product->updated_at?->toIso8601String(),
                ];
            }
        }

        return $alerts;
    }
}
