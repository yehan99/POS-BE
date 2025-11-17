<?php

namespace Database\Seeders;

use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StockAdjustmentSeeder extends Seeder
{
    private const ADJUSTMENT_COUNT = 20;

    public function run(): void
    {
        $faker = fake();

        $products = Product::query()
            ->select(['id', 'name', 'sku', 'cost_price', 'stock_quantity'])
            ->get();

        if ($products->isEmpty()) {
            $products = $this->seedFallbackProducts($faker);
        }

        $locations = InventoryLocation::query()->get();
        if ($locations->isEmpty()) {
            $locations = $this->seedLocations($faker);
        }

        if ($products->isEmpty() || $locations->isEmpty()) {
            return;
        }

        $types = ['increase', 'decrease', 'damage', 'loss', 'found', 'return', 'correction'];

        $reasonMap = [
            'increase' => ['Cycle count adjustment', 'Supplier reconciliation', 'Inventory sync correction'],
            'decrease' => ['Shrinkage recorded', 'System variance', 'Damaged in handling'],
            'damage' => ['Broken during transit', 'Expired stock removal', 'Packaging damage'],
            'loss' => ['Lost in transit', 'Unaccounted stock variance', 'Missing during audit'],
            'found' => ['Stock recovered', 'Over-delivery identified', 'Returned to shelf'],
            'return' => ['Customer return', 'Supplier return', 'Warranty replacement'],
            'correction' => ['Data entry correction', 'Unit of measure change', 'Inventory recount'],
        ];

        $statusPool = [
            StockAdjustment::STATUS_APPROVED,
            StockAdjustment::STATUS_APPROVED,
            StockAdjustment::STATUS_PENDING,
            StockAdjustment::STATUS_PENDING,
            StockAdjustment::STATUS_REJECTED,
        ];

        DB::transaction(function () use ($faker, $types, $reasonMap, $statusPool, $locations) {
            $created = 0;

            while ($created < self::ADJUSTMENT_COUNT) {
                $product = Product::query()
                    ->select(['id', 'name', 'sku', 'cost_price', 'stock_quantity'])
                    ->inRandomOrder()
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    break;
                }

                $type = Arr::random($types);
                $quantity = $faker->numberBetween(1, 40);

                if (in_array($type, ['decrease', 'damage', 'loss'], true) && $product->stock_quantity <= 5) {
                    $type = 'increase';
                }

                $change = $this->resolveQuantityChange($type, $quantity);

                if ($change < 0 && abs($change) > $product->stock_quantity) {
                    if ($product->stock_quantity === 0) {
                        continue;
                    }

                    $change = -$product->stock_quantity;
                    $quantity = abs($change);
                }

                if ($quantity <= 0) {
                    continue;
                }

                $previousStock = (int) $product->stock_quantity;
                $newStock = max(0, $previousStock + $change);
                $unitCost = max((float) ($product->cost_price ?? $faker->randomFloat(2, 50, 500)), 1.0);
                $status = Arr::random($statusPool);

                $createdAt = Carbon::now()
                    ->subDays($faker->numberBetween(0, 120))
                    ->setTime(
                        $faker->numberBetween(0, 23),
                        $faker->numberBetween(0, 59),
                        $faker->numberBetween(0, 59)
                    );

                $approvedAt = null;
                $approvedBy = null;
                $rejectedAt = null;
                $rejectedBy = null;
                $rejectionReason = null;

                if ($status === StockAdjustment::STATUS_APPROVED) {
                    $approvedAt = (clone $createdAt)->addHours($faker->numberBetween(1, 72));
                    $approvedBy = $faker->name();

                    $product->stock_quantity = $newStock;
                    $product->save();
                } elseif ($status === StockAdjustment::STATUS_REJECTED) {
                    $rejectedAt = (clone $createdAt)->addHours($faker->numberBetween(1, 48));
                    $rejectedBy = $faker->name();
                    $rejectionReason = $faker->randomElement([
                        'Supporting documents missing',
                        'Quantity mismatch detected',
                        'Awaiting supervisor review',
                    ]);
                }

                $location = $locations->random();

                $reasonChoices = $reasonMap[$type] ?? ['Inventory adjustment'];
                $reason = Arr::random($reasonChoices);

                $adjustment = StockAdjustment::create([
                    'adjustment_number' => StockAdjustment::generateNumber(),
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'location_id' => $location->id,
                    'adjustment_type' => $type,
                    'quantity' => $quantity,
                    'quantity_change' => $change,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reason' => $reason,
                    'notes' => $faker->boolean(30) ? $faker->sentence() : null,
                    'unit_cost' => $unitCost,
                    'total_value' => round($quantity * $unitCost, 2),
                    'value_change' => round($change * $unitCost, 2),
                    'status' => $status,
                    'created_by' => $faker->name(),
                    'approved_by' => $approvedBy,
                    'approved_at' => $approvedAt,
                    'rejected_by' => $rejectedBy,
                    'rejection_reason' => $rejectionReason,
                    'rejected_at' => $rejectedAt,
                    'meta' => [
                        'reference' => strtoupper($faker->bothify('REF-###??')),
                        'source' => Arr::random(['cycle_count', 'manual_entry', 'integration']),
                    ],
                ]);

                $adjustment->created_at = $createdAt;
                $adjustment->updated_at = collect([$approvedAt, $rejectedAt, $createdAt])
                    ->filter()
                    ->sort()
                    ->last() ?? $createdAt;
                $adjustment->save();

                $created++;
            }
        });
    }

    private function resolveQuantityChange(string $type, int $quantity): int
    {
        $negativeTypes = ['decrease', 'damage', 'loss'];

        return in_array($type, $negativeTypes, true) ? -$quantity : $quantity;
    }

    private function seedLocations($faker)
    {
        $samples = collect([
            ['code' => 'WH-001', 'name' => 'Central Warehouse'],
            ['code' => 'WH-002', 'name' => 'Secondary Warehouse'],
            ['code' => 'ST-001', 'name' => 'Galle Retail'],
            ['code' => 'ST-002', 'name' => 'Kandy Retail'],
        ]);

        foreach ($samples as $sample) {
            InventoryLocation::firstOrCreate([
                'code' => $sample['code'],
            ], [
                'name' => $sample['name'],
                'type' => str_contains($sample['code'], 'WH') ? 'warehouse' : 'store',
                'address' => [
                    'line1' => $faker->streetAddress(),
                    'city' => $faker->city(),
                    'country' => 'Sri Lanka',
                ],
                'is_active' => true,
                'capacity' => $faker->numberBetween(1000, 5000),
                'current_utilization' => $faker->numberBetween(100, 900),
                'manager' => $faker->name(),
                'phone' => $faker->phoneNumber(),
                'email' => $faker->unique()->safeEmail(),
            ]);
        }

        return InventoryLocation::whereIn('code', $samples->pluck('code'))
            ->get(['id', 'name', 'code']);
    }

    private function seedFallbackProducts($faker)
    {
        $skus = [];

        foreach (range(1, 20) as $index) {
            $sku = 'SA-FB-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $skus[] = $sku;

            Product::query()->firstOrCreate([
                'sku' => $sku,
            ], [
                'name' => 'Sample Product ' . $index,
                'price' => $faker->randomFloat(2, 200, 1200),
                'cost_price' => $faker->randomFloat(2, 90, 600),
                'stock_quantity' => $faker->numberBetween(20, 200),
                'is_active' => true,
                'track_inventory' => true,
            ]);
        }

        return Product::whereIn('sku', $skus)
            ->get(['id', 'name', 'sku', 'cost_price', 'stock_quantity']);
    }
}
