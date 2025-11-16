<?php

namespace Database\Seeders;

use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\StockTransfer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockTransferSeeder extends Seeder
{
    private const TRANSFER_COUNT = 100;

    public function run(): void
    {
        $faker = fake();

        $locations = InventoryLocation::query()->get();
        if ($locations->count() < 2) {
            $locations = $this->seedLocations($faker);
        }

        $products = Product::query()
            ->select(['id', 'name', 'sku', 'cost_price'])
            ->get();

        if ($products->isEmpty()) {
            $products = $this->seedFallbackProducts($faker);
        }

        $statuses = [
            StockTransfer::STATUS_PENDING,
            StockTransfer::STATUS_APPROVED,
            StockTransfer::STATUS_IN_TRANSIT,
            StockTransfer::STATUS_COMPLETED,
            StockTransfer::STATUS_CANCELLED,
        ];

        DB::transaction(function () use ($faker, $locations, $products, $statuses) {
            for ($i = 0; $i < self::TRANSFER_COUNT; $i++) {
                [$fromLocation, $toLocation] = $this->pickLocations($locations);

                $items = $this->buildItems($faker, $products);
                $totals = $this->calculateTotals($items);

                $status = Arr::random($statuses);
                $timestamps = $this->deriveTimestamps($faker, $status);

                $transfer = StockTransfer::create([
                    'transfer_number' => StockTransfer::generateNumber(),
                    'from_location_id' => $fromLocation->id,
                    'to_location_id' => $toLocation->id,
                    'status' => $status,
                    'total_items' => $totals['items'],
                    'total_value' => $totals['value'],
                    'requested_by' => $faker->name(),
                    'approved_by' => $timestamps['approvedBy'],
                    'shipped_by' => $timestamps['shippedBy'],
                    'received_by' => $timestamps['receivedBy'],
                    'notes' => $faker->boolean(30) ? $faker->sentence() : null,
                    'approved_at' => $timestamps['approvedAt'],
                    'shipped_at' => $timestamps['shippedAt'],
                    'received_at' => $timestamps['receivedAt'],
                    'cancelled_at' => $timestamps['cancelledAt'],
                    'cancel_reason' => $timestamps['cancelReason'],
                    'meta' => [
                        'priority' => Arr::random(['normal', 'high', 'urgent']),
                        'requested_via' => Arr::random(['dashboard', 'mobile_app']),
                    ],
                ]);

                $transfer->created_at = $timestamps['createdAt'];
                $transfer->updated_at = $this->resolveUpdatedAt($timestamps);
                $transfer->save();

                $transfer->items()->createMany($items);
            }
        });
    }

    private function buildItems($faker, $products): array
    {
        $items = [];
        $itemCount = $faker->numberBetween(2, 5);

        for ($index = 0; $index < $itemCount; $index++) {
            $product = $products->random();
            $quantity = $faker->numberBetween(5, 80);
            $unitCost = max((float) ($product->cost_price ?? $faker->randomFloat(2, 150, 1500)), 10.0);
            $totalCost = round($quantity * $unitCost, 2);

            $items[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
                'received_quantity' => $faker->numberBetween(0, $quantity),
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'meta' => [
                    'batch' => Str::upper(Str::random(6)),
                ],
            ];
        }

        return $items;
    }

    private function calculateTotals(array $items): array
    {
        $totalItems = 0;
        $totalValue = 0.0;

        foreach ($items as $item) {
            $totalItems += (int) $item['quantity'];
            $totalValue += (float) $item['total_cost'];
        }

        return [
            'items' => $totalItems,
            'value' => round($totalValue, 2),
        ];
    }

    private function deriveTimestamps($faker, string $status): array
    {
        $createdAt = Carbon::now()->subDays($faker->numberBetween(0, 120))->setTimeFromTimeString($faker->time('H:i:s'));
        $approvedAt = null;
        $shippedAt = null;
        $receivedAt = null;
        $cancelledAt = null;
        $approvedBy = null;
        $shippedBy = null;
        $receivedBy = null;
        $cancelReason = null;

        if (in_array($status, [StockTransfer::STATUS_APPROVED, StockTransfer::STATUS_IN_TRANSIT, StockTransfer::STATUS_COMPLETED, StockTransfer::STATUS_CANCELLED], true)) {
            $approvedAt = (clone $createdAt)->addDays($faker->numberBetween(0, 3));
            $approvedBy = $faker->name();
        }

        if (in_array($status, [StockTransfer::STATUS_IN_TRANSIT, StockTransfer::STATUS_COMPLETED, StockTransfer::STATUS_CANCELLED], true)) {
            $shippedAt = $approvedAt?->copy()->addDays($faker->numberBetween(0, 3));
            $shippedBy = $faker->name();
        }

        if ($status === StockTransfer::STATUS_COMPLETED) {
            $receivedAt = $shippedAt?->copy()->addDays($faker->numberBetween(0, 3));
            $receivedBy = $faker->name();
        }

        if ($status === StockTransfer::STATUS_CANCELLED) {
            $cancelledAt = $approvedAt?->copy()->addDays($faker->numberBetween(0, 2));
            $cancelReason = $faker->randomElement([
                'Damaged items reported',
                'Destination closed temporarily',
                'Inventory mismatch detected',
            ]);
        }

        return [
            'createdAt' => $createdAt,
            'approvedAt' => $approvedAt,
            'shippedAt' => $shippedAt,
            'receivedAt' => $receivedAt,
            'cancelledAt' => $cancelledAt,
            'approvedBy' => $approvedBy,
            'shippedBy' => $shippedBy,
            'receivedBy' => $receivedBy,
            'cancelReason' => $cancelReason,
        ];
    }

    private function pickLocations($locations): array
    {
        if ($locations->count() < 2) {
            throw new \RuntimeException('At least two inventory locations are required to seed stock transfers.');
        }

        $from = $locations->random();
        do {
            $to = $locations->random();
        } while ($to->is($from));

        return [$from, $to];
    }

    private function seedLocations($faker)
    {
        $samples = collect([
            ['code' => 'WH-001', 'name' => 'Central Warehouse'],
            ['code' => 'WH-002', 'name' => 'Distribution Hub'],
            ['code' => 'ST-001', 'name' => 'Colombo Store'],
            ['code' => 'ST-002', 'name' => 'Kandy Store'],
            ['code' => 'ST-003', 'name' => 'Galle Store'],
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

        return InventoryLocation::whereIn('code', $samples->pluck('code'))->get();
    }

    private function seedFallbackProducts($faker)
    {
        $skus = [];

        foreach (range(1, 20) as $index) {
            $sku = 'FB-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT);
            $skus[] = $sku;

            Product::query()->firstOrCreate([
                'sku' => $sku,
            ], [
                'name' => 'Fallback Product ' . $index,
                'barcode' => $faker->ean13(),
                'status' => 'active',
                'cost_price' => $faker->randomFloat(2, 250, 2500),
                'sale_price' => $faker->randomFloat(2, 500, 3500),
                'stock' => $faker->numberBetween(50, 500),
                'reorder_point' => $faker->numberBetween(20, 80),
            ]);
        }

        return Product::whereIn('sku', $skus)->get(['id', 'name', 'sku', 'cost_price']);
    }

    private function resolveUpdatedAt(array $timestamps): Carbon
    {
        $candidates = array_filter([
            $timestamps['cancelledAt'],
            $timestamps['receivedAt'],
            $timestamps['shippedAt'],
            $timestamps['approvedAt'],
            $timestamps['createdAt'],
        ]);

        $sorted = collect($candidates)
            ->filter()
            ->sortBy(fn (Carbon $time) => $time->getTimestamp());

        return $sorted->last() ?? $timestamps['createdAt'];
    }
}
