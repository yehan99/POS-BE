<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Supplier>
     */
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $faker = $this->faker;

        $categories = [
            'Food & Beverages',
            'Electronics',
            'Hardware',
            'Packaging',
            'Cleaning & Hygiene',
            'Office Supplies',
            'Logistics',
            'Other',
        ];

        $status = $faker->randomElement(['active', 'active', 'active', 'inactive', 'blocked']);
        $isActive = $status === 'active' ? true : ($status === 'inactive' ? false : $faker->boolean(70));
        $isPreferred = $faker->boolean(30);

        $monthlyStats = [];
        $totalSpent = 0;
        $totalOrders = 0;

        for ($i = 0; $i < 6; $i++) {
            $period = Carbon::now()->subMonths($i)->format('Y-m');
            $purchaseOrders = $faker->numberBetween(2, 18);
            $periodSpend = $faker->numberBetween(150000, 750000);
            $averageLead = $faker->randomFloat(2, 2, 12);

            $monthlyStats[] = [
                'period' => $period,
                'totalSpend' => $periodSpend,
                'purchaseOrders' => $purchaseOrders,
                'averageLeadTimeDays' => $averageLead,
            ];

            $totalSpent += $periodSpend;
            $totalOrders += $purchaseOrders;
        }

        $spendThisMonth = $monthlyStats[0]['totalSpend'] ?? 0;
        $spendLastMonth = $monthlyStats[1]['totalSpend'] ?? $spendThisMonth;

        return [
            'id' => (string) Str::uuid(),
            'supplier_code' => Supplier::generateCode(),
            'name' => $faker->company(),
            'contact_person' => $faker->name(),
            'email' => $faker->unique()->companyEmail(),
            'phone' => $faker->unique()->numerify('+94#########'),
            'category' => $faker->randomElement($categories),
            'status' => $status,
            'is_active' => $isActive,
            'is_preferred' => $isPreferred,
            'payment_terms' => $faker->randomElement(['Net 15', 'Net 30', 'Net 45', 'Net 60']),
            'credit_limit' => $faker->optional(0.7)->numberBetween(500000, 5000000),
            'tax_id' => $faker->optional(0.6)->bothify('TIN-########'),
            'website' => $faker->optional()->url(),
            'address' => [
                'street' => $faker->streetAddress(),
                'city' => $faker->city(),
                'state' => $faker->state(),
                'postalCode' => $faker->postcode(),
                'country' => $faker->country(),
            ],
            'bank_details' => [
                'bankName' => $faker->company() . ' Bank',
                'accountNumber' => $faker->bankAccountNumber(),
                'accountName' => $faker->company(),
                'branchCode' => $faker->numerify('BR###'),
                'swiftCode' => strtoupper($faker->bothify('SWIFT##??')),
            ],
            'notes' => $faker->optional()->sentence(12),
            'rating' => $faker->randomFloat(2, 3, 5),
            'total_purchases' => $totalOrders,
            'total_orders' => $totalOrders,
            'total_spent' => $totalSpent,
            'spend_this_month' => $spendThisMonth,
            'spend_last_month' => $spendLastMonth,
            'on_time_delivery_rate' => $faker->randomFloat(2, 75, 99),
            'average_lead_time_days' => $faker->randomFloat(2, 3, 10),
            'last_purchase_at' => Carbon::now()->subDays($faker->numberBetween(1, 45)),
            'monthly_spend_stats' => $monthlyStats,
        ];
    }
}
