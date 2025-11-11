<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' POS',
            'business_type' => fake()->randomElement([
                'retail',
                'restaurant',
                'salon',
                'grocery',
                'pharmacy',
                'electronics',
                'clothing',
                'other',
            ]),
            'country' => 'LK',
            'phone' => fake()->numerify('+94#########'),
            'settings' => [
                'currency' => 'LKR',
                'timezone' => 'Asia/Colombo',
                'language' => 'en',
            ],
            'is_active' => true,
        ];
    }
}
