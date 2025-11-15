<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));

        $metadataOptions = [
            ['displayColor' => $this->faker->hexColor()],
            ['icon' => $this->faker->randomElement(['inventory', 'local_offer', 'category', 'storefront'])],
            [
                'displayColor' => $this->faker->hexColor(),
                'icon' => $this->faker->randomElement(['inventory', 'local_offer', 'category', 'storefront']),
            ],
        ];

        return [
            'tenant_id' => null,
            'parent_id' => null,
            'name' => $name,
            'slug' => null,
            'description' => $this->faker->optional(0.5)->sentence(12),
            'sort_order' => 0,
            'is_active' => $this->faker->boolean(85),
            'image' => null,
            'metadata' => $this->faker->optional(0.3)->randomElement($metadataOptions),
        ];
    }
}
