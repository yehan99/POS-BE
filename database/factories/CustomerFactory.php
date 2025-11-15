<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Customer>
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $faker = $this->faker;

        $firstName = $faker->firstName();
        $lastName = $faker->lastName();

        return [
            'id' => (string) Str::uuid(),
            'customer_code' => Customer::generateCode(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $faker->unique()->safeEmail(),
            'phone' => $faker->unique()->numerify('07########'),
            'date_of_birth' => $faker->optional()->date(),
            'gender' => $faker->optional()->randomElement(['male', 'female', 'other']),
            'address' => [
                'street' => $faker->streetAddress(),
                'city' => $faker->city(),
                'state' => $faker->state(),
                'postalCode' => $faker->postcode(),
                'country' => $faker->country(),
            ],
            'loyalty_points' => $faker->numberBetween(0, 5000),
            'loyalty_tier' => $faker->randomElement(['bronze', 'silver', 'gold', 'platinum']),
            'total_purchases' => $faker->numberBetween(0, 120),
            'total_spent' => $faker->randomFloat(2, 0, 750000),
            'last_purchase_at' => $faker->optional()->dateTimeBetween('-1 year'),
            'notes' => $faker->optional()->sentence(8),
            'is_active' => $faker->boolean(85),
        ];
    }
}
