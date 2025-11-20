<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(3, true));
        $price = $this->faker->randomFloat(2, 5, 500);
        $loyaltyPrice = round($price * $this->faker->randomFloat(2, 0.7, 0.98), 2);
        $costPrice = $this->faker->randomFloat(2, max(1, $price * 0.4), max($price * 0.8, 1));
        $trackInventory = $this->faker->boolean(80);
        $stockQuantity = $trackInventory ? $this->faker->numberBetween(0, 250) : 0;
        $reorderLevel = $trackInventory ? $this->faker->numberBetween(0, min(60, $stockQuantity)) : null;
        $maxStockLevel = $trackInventory ? ($stockQuantity + $this->faker->numberBetween(20, 200)) : null;
        $taxClasses = [
            [
                'id' => 'standard',
                'name' => 'Standard Tax (15%)',
                'rate' => 15,
                'type' => 'PERCENTAGE',
                'isActive' => true,
            ],
            [
                'id' => 'reduced',
                'name' => 'Reduced Tax (8%)',
                'rate' => 8,
                'type' => 'PERCENTAGE',
                'isActive' => true,
            ],
            [
                'id' => 'zero',
                'name' => 'Zero Tax (0%)',
                'rate' => 0,
                'type' => 'PERCENTAGE',
                'isActive' => true,
            ],
        ];

        $attributes = [
            [
                'name' => 'Color',
                'value' => ucfirst($this->faker->safeColorName()),
                'type' => 'text',
            ],
            [
                'name' => 'Size',
                'value' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
                'type' => 'select',
            ],
        ];

        if ($this->faker->boolean(40)) {
            $attributes[] = [
                'name' => 'Material',
                'value' => ucfirst($this->faker->word()),
                'type' => 'text',
            ];
        }

        $tags = collect($this->faker->words($this->faker->numberBetween(1, 4)))
            ->map(fn (string $tag) => Str::of($tag)->slug('_')->toString())
            ->unique()
            ->values()
            ->all();

        $dimensions = $this->faker->boolean(60)
            ? [
                'length' => $this->faker->randomFloat(2, 5, 80),
                'width' => $this->faker->randomFloat(2, 5, 80),
                'height' => $this->faker->randomFloat(2, 1, 60),
                'unit' => $this->faker->randomElement(['cm', 'mm', 'in']),
            ]
            : null;

        $barcode = $this->faker->boolean(60) ? $this->faker->unique()->ean13() : null;

        return [
            'tenant_id' => null,
            'category_id' => null,
            'sku' => 'SKU-'.Str::upper($this->faker->unique()->bothify('########')),
            'name' => $name,
            'description' => $this->faker->optional(0.6)->paragraph(),
            'brand' => $this->faker->optional(0.5)->company(),
            'barcode' => $barcode,
            'price' => $price,
            'loyalty_price' => $loyaltyPrice,
            'cost_price' => $costPrice,
            'tax_class' => $this->faker->randomElement($taxClasses),
            'is_active' => $this->faker->boolean(90),
            'track_inventory' => $trackInventory,
            'stock_quantity' => $stockQuantity,
            'reorder_level' => $reorderLevel,
            'max_stock_level' => $maxStockLevel,
            'weight' => $this->faker->optional(0.5)->randomFloat(2, 0.1, 10),
            'dimensions' => $dimensions,
            'images' => $this->faker->boolean(70)
                ? [
                    $this->faker->imageUrl(800, 600, 'technics', true),
                    $this->faker->imageUrl(800, 600, 'business', true),
                ]
                : [],
            'variants' => [],
            'attributes' => $attributes,
            'tags' => $tags,
        ];
    }
}
