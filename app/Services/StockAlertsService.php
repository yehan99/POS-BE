<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class StockAlertsService
{
    /**
     * Get paginated stock alerts with filters
     */
    public function getAlerts(array $filters): array
    {
        $page = max(1, $filters['page'] ?? 1);
        $perPage = min(100, max(5, $filters['per_page'] ?? 10));
        $offset = ($page - 1) * $perPage;

        // Build the alerts query
        $alerts = $this->buildAlertsQuery($filters);

        $total = count($alerts);
        $paginatedAlerts = array_slice($alerts, $offset, $perPage);

        return [
            'data' => $paginatedAlerts,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Build alerts array from products
     */
    protected function buildAlertsQuery(array $filters): array
    {
        $products = Product::query()
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->get();

        $alerts = [];

        foreach ($products as $product) {
            // Out of stock alerts
            if ($product->stock_quantity <= 0) {
                $alerts[] = $this->createAlert(
                    $product,
                    'out_of_stock',
                    'critical',
                    "Product is out of stock"
                );
            }
            // Low stock alerts
            elseif ($product->reorder_level && $product->stock_quantity <= $product->reorder_level) {
                $alerts[] = $this->createAlert(
                    $product,
                    'low_stock',
                    $product->stock_quantity <= ($product->reorder_level * 0.5) ? 'high' : 'medium',
                    "Stock level is below reorder point ({$product->reorder_level})"
                );
            }
            // Overstock alerts (if stock is 3x above reorder level)
            elseif ($product->reorder_level && $product->stock_quantity >= ($product->reorder_level * 3)) {
                $alerts[] = $this->createAlert(
                    $product,
                    'overstock',
                    'low',
                    "Stock level is significantly above reorder point"
                );
            }
        }

        // Apply filters
        if (!empty($filters['type'])) {
            $alerts = array_filter($alerts, fn($alert) => $alert['alertType'] === $filters['type']);
        }

        if (!empty($filters['status'])) {
            $alerts = array_filter($alerts, fn($alert) => $alert['status'] === $filters['status']);
        }

        if (!empty($filters['severity'])) {
            $alerts = array_filter($alerts, fn($alert) => $alert['severity'] === $filters['severity']);
        }

        // Sort by severity and created date
        usort($alerts, function ($a, $b) {
            $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            $severityCompare = $severityOrder[$a['severity']] <=> $severityOrder[$b['severity']];

            if ($severityCompare !== 0) {
                return $severityCompare;
            }

            return $b['createdAt'] <=> $a['createdAt'];
        });

        return array_values($alerts);
    }

    /**
     * Create an alert object
     */
    protected function createAlert(
        Product $product,
        string $type,
        string $severity,
        string $message
    ): array {
        return [
            'id' => "alert_{$type}_{$product->id}",
            'alertType' => $type,
            'productId' => (string) $product->id,
            'productName' => $product->name,
            'productSku' => $product->sku,
            'currentStock' => $product->stock_quantity,
            'threshold' => $product->reorder_level,
            'expiryDate' => null,
            'locationId' => null,
            'locationName' => 'Main Warehouse',
            'severity' => $severity,
            'status' => 'active',
            'message' => $message,
            'acknowledgedBy' => null,
            'acknowledgedAt' => null,
            'createdAt' => now()->toISOString(),
        ];
    }

    /**
     * Acknowledge an alert
     */
    public function acknowledgeAlert(string $id, string $acknowledgedBy): ?array
    {
        // In a real implementation, you would update a database record
        // For now, we'll simulate the acknowledgment
        $alert = $this->findAlertById($id);

        if (!$alert) {
            return null;
        }

        $alert['status'] = 'acknowledged';
        $alert['acknowledgedBy'] = $acknowledgedBy;
        $alert['acknowledgedAt'] = now()->toISOString();

        return $alert;
    }

    /**
     * Resolve an alert
     */
    public function resolveAlert(string $id, string $resolvedBy, ?string $notes = null): ?array
    {
        // In a real implementation, you would update a database record
        $alert = $this->findAlertById($id);

        if (!$alert) {
            return null;
        }

        $alert['status'] = 'resolved';
        $alert['resolvedBy'] = $resolvedBy;
        $alert['resolvedAt'] = now()->toISOString();
        $alert['resolutionNotes'] = $notes;

        return $alert;
    }

    /**
     * Bulk resolve alerts
     */
    public function bulkResolveAlerts(array $ids, string $resolvedBy, ?string $notes = null): int
    {
        $count = 0;

        foreach ($ids as $id) {
            $alert = $this->resolveAlert($id, $resolvedBy, $notes);
            if ($alert) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get alert summary/statistics
     */
    public function getAlertSummary(): array
    {
        $alerts = $this->buildAlertsQuery([]);

        $summary = [
            'total' => count($alerts),
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'active' => 0,
            'acknowledged' => 0,
            'resolved' => 0,
            'byType' => [
                'out_of_stock' => 0,
                'low_stock' => 0,
                'overstock' => 0,
                'expiring_soon' => 0,
                'expired' => 0,
            ],
        ];

        foreach ($alerts as $alert) {
            // Count by severity
            $summary[$alert['severity']]++;

            // Count by status
            $summary[$alert['status']]++;

            // Count by type
            if (isset($summary['byType'][$alert['alertType']])) {
                $summary['byType'][$alert['alertType']]++;
            }
        }

        return $summary;
    }

    /**
     * Find alert by ID (helper method)
     */
    protected function findAlertById(string $id): ?array
    {
        $alerts = $this->buildAlertsQuery([]);

        foreach ($alerts as $alert) {
            if ($alert['id'] === $id) {
                return $alert;
            }
        }

        return null;
    }
}
