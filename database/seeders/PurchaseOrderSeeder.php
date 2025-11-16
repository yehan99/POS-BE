<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderSeeder extends Seeder
{
    private const TARGET_COUNT = 100;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (PurchaseOrder::count() >= self::TARGET_COUNT) {
            return;
        }

        $suppliers = Supplier::all();
        if ($suppliers->isEmpty()) {
            $this->command?->warn('Skipping purchase order seeder: no suppliers found.');
            return;
        }

        $products = Product::all();
        $faker = fake('en_US');
        $statuses = ['pending', 'approved', 'ordered', 'partially_received', 'received', 'cancelled'];
        $paymentStatuses = ['unpaid', 'partial', 'paid', 'overdue'];
        $paymentMethods = ['bank_transfer', 'cash', 'credit_terms', 'card'];

        $ordersToCreate = self::TARGET_COUNT - PurchaseOrder::count();

        for ($index = 0; $index < $ordersToCreate; $index++) {
            $supplier = $suppliers->random();
            $status = Arr::random($statuses);
            $paymentStatus = match ($status) {
                'received' => Arr::random(['paid', 'partial']),
                'partially_received' => Arr::random(['partial', 'unpaid']),
                'cancelled' => 'unpaid',
                default => Arr::random($paymentStatuses),
            };

            $createdAt = Carbon::now()->subDays(random_int(0, 180))->setTime(random_int(8, 18), random_int(0, 59));
            $expectedDelivery = (clone $createdAt)->addDays(random_int(5, 21));
            $approvedAt = in_array($status, ['approved', 'ordered', 'partially_received', 'received'], true)
                ? (clone $createdAt)->addDays(random_int(1, 3))
                : null;
            $orderedAt = in_array($status, ['ordered', 'partially_received', 'received'], true)
                ? ($approvedAt?->copy() ?? (clone $createdAt)->addDays(2))
                : null;
            $receivedAt = in_array($status, ['partially_received', 'received'], true)
                ? ($orderedAt?->copy()->addDays(random_int(3, 10)))
                : null;
            $actualDelivery = $status === 'received'
                ? ($expectedDelivery->copy()->subDays(random_int(-2, 3)))
                : ($status === 'partially_received' ? null : null);

            $items = $this->buildItems($products, $faker, $status);
            $totals = $this->calculateTotals($items);

            $shipping = round($totals['subtotal'] * Arr::random([0.02, 0.035, 0.05]), 2);
            $orderDiscount = round($totals['subtotal'] * Arr::random([0.0, 0.01, 0.015]), 2);

            $po = new PurchaseOrder([
                'po_number' => PurchaseOrder::generateNumber(),
                'supplier_id' => $supplier->id,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'payment_method' => Arr::random($paymentMethods),
                'subtotal' => $totals['subtotal'],
                'tax' => $totals['tax'],
                'discount' => $orderDiscount,
                'shipping_cost' => $shipping,
                'total' => max($totals['subtotal'] + $totals['tax'] - $orderDiscount + $shipping, 0),
                'expected_delivery_date' => $expectedDelivery,
                'actual_delivery_date' => $actualDelivery,
                'approved_at' => $approvedAt,
                'ordered_at' => $orderedAt,
                'received_at' => $status === 'received' ? $receivedAt : null,
                'cancelled_at' => $status === 'cancelled' ? ($createdAt->copy()->addDays(random_int(1, 4))) : null,
                'cancel_reason' => $status === 'cancelled' ? Arr::random([
                    'Budget reallocation',
                    'Supplier unable to fulfill',
                    'Item discontinued',
                ]) : null,
                'created_by' => Arr::random(['Operations Team', 'Procurement Bot', 'System User']),
                'approved_by' => $approvedAt ? Arr::random(['Procurement Manager', 'Inventory Lead']) : null,
                'received_by' => $status === 'received' ? Arr::random(['Warehouse Lead', 'Receiving Clerk']) : null,
                'notes' => $faker->optional(0.35)->sentence(12),
                'terms_and_conditions' => $faker->optional(0.25)->paragraph(),
                'meta' => [
                    'paymentDueDate' => $expectedDelivery->copy()->addDays(15)->toDateString(),
                ],
            ]);

            $po->created_at = $createdAt;
            $po->updated_at = $createdAt->copy()->addDays(random_int(0, 15));

            DB::transaction(function () use ($po, $items) {
                $po->save();
                foreach ($items as $item) {
                    $po->items()->create($item);
                }
            });
        }

        $this->updateSupplierSnapshots();
    }

    /**
     * Build line items for the seeded purchase order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildItems($products, $faker, string $status): array
    {
        $itemCount = random_int(3, 7);
        $items = [];

        for ($i = 0; $i < $itemCount; $i++) {
            $product = $products->isNotEmpty() ? $products->random() : null;
            $quantity = random_int(5, 60);
            $unitCost = $product?->cost_price ? (float) $product->cost_price : round(random_int(2500, 22000) / 1.13, 2);
            $tax = round($unitCost * $quantity * Arr::random([0.05, 0.08, 0.1]), 2);
            $lineDiscount = round($unitCost * $quantity * Arr::random([0.0, 0.02, 0.03]), 2);
            $receivedQuantity = in_array($status, ['received', 'partially_received'], true)
                ? ($status === 'received' ? $quantity : random_int((int) ($quantity / 2), $quantity - 1))
                : 0;

            $items[] = [
                'product_id' => $product?->id,
                'product_name' => $product?->name ?? Str::headline($faker->words(3, true)),
                'product_sku' => $product?->sku,
                'quantity' => $quantity,
                'received_quantity' => $receivedQuantity,
                'unit_cost' => $unitCost,
                'tax' => $tax,
                'discount' => $lineDiscount,
                'total' => max($quantity * $unitCost + $tax - $lineDiscount, 0),
            ];
        }

        return $items;
    }

    /**
     * Calculate totals for seeded items.
     */
    private function calculateTotals(array $items): array
    {
        $subtotal = 0;
        $tax = 0;

        foreach ($items as $item) {
            $subtotal += (int) $item['quantity'] * (float) $item['unit_cost'];
            $tax += (float) $item['tax'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
        ];
    }

    /**
     * Update supplier metrics after seeding purchase orders.
     */
    private function updateSupplierSnapshots(): void
    {
        $snapshots = PurchaseOrder::select(
            'supplier_id',
            DB::raw('COUNT(*) as total_orders'),
            DB::raw('SUM(total) as total_value'),
            DB::raw('SUM(CASE WHEN status = "received" THEN 1 ELSE 0 END) as fulfilled_orders')
        )
            ->groupBy('supplier_id')
            ->get();

        foreach ($snapshots as $row) {
            Supplier::where('id', $row->supplier_id)->update([
                'total_orders' => (int) $row->total_orders,
                'total_spent' => (float) $row->total_value,
                'total_purchases' => (int) $row->fulfilled_orders,
                'last_purchase_at' => Carbon::now()->subDays(random_int(0, 30)),
            ]);
        }
    }
}
