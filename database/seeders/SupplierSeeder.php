<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $existingCount = Supplier::query()->count();

        if ($existingCount >= 50) {
            return;
        }

        Supplier::factory()
            ->count(50 - $existingCount)
            ->create();
    }
}
