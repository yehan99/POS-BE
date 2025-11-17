<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalesTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get sample data
        $tenant = Tenant::first();
        $cashier = User::first();
        $customer = Customer::first();
        $products = Product::take(5)->get();

        if (!$tenant || !$cashier || $products->isEmpty()) {
            $this->command->warn('Required data not found. Please seed tenants, users, and products first.');
            return;
        }

        $this->command->info('Creating sample sales transactions...');

        // Transaction 1: Cash payment, with customer
        $items1 = [
            [
                'product_id' => $products[0]->id,
                'name' => $products[0]->name,
                'sku' => $products[0]->sku,
                'quantity' => 2,
                'price' => $products[0]->price,
                'total' => $products[0]->price * 2,
            ],
            [
                'product_id' => $products[1]->id,
                'name' => $products[1]->name,
                'sku' => $products[1]->sku,
                'quantity' => 1,
                'price' => $products[1]->price,
                'total' => $products[1]->price,
            ],
        ];

        $subtotal1 = collect($items1)->sum('total');
        $taxAmount1 = $subtotal1 * 0.10; // 10% tax
        $total1 = $subtotal1 + $taxAmount1;

        SalesTransaction::create([
            'transaction_date' => now()->subHours(2),
            'items' => $items1,
            'subtotal' => $subtotal1,
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 10.00,
            'tax_amount' => $taxAmount1,
            'total' => $total1,
            'amount_paid' => ceil($total1 / 10) * 10, // Round up to nearest 10
            'change' => (ceil($total1 / 10) * 10) - $total1,
            'payment_method' => 'cash',
            'payment_details' => [
                'cashReceived' => ceil($total1 / 10) * 10,
            ],
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name ?? 'Walk-in Customer',
            'cashier_id' => $cashier->id,
            'cashier_name' => $cashier->name,
            'tenant_id' => $tenant->id,
            'store_name' => 'Main Store',
            'status' => 'completed',
        ]);

        // Transaction 2: Card payment, no customer
        $items2 = [
            [
                'product_id' => $products[2]->id,
                'name' => $products[2]->name,
                'sku' => $products[2]->sku,
                'quantity' => 3,
                'price' => $products[2]->price,
                'total' => $products[2]->price * 3,
            ],
        ];

        $subtotal2 = collect($items2)->sum('total');
        $discountAmount2 = $subtotal2 * 0.05; // 5% discount
        $afterDiscount2 = $subtotal2 - $discountAmount2;
        $taxAmount2 = $afterDiscount2 * 0.10;
        $total2 = $afterDiscount2 + $taxAmount2;

        SalesTransaction::create([
            'transaction_date' => now()->subHours(1),
            'items' => $items2,
            'subtotal' => $subtotal2,
            'discount_type' => 'percentage',
            'discount_value' => 5.00,
            'discount_amount' => $discountAmount2,
            'tax_rate' => 10.00,
            'tax_amount' => $taxAmount2,
            'total' => $total2,
            'amount_paid' => $total2,
            'change' => 0,
            'payment_method' => 'card',
            'payment_details' => [
                'cardType' => 'Visa',
                'cardLast4' => '4242',
                'cardReference' => 'TXN-' . now()->format('YmdHis'),
            ],
            'customer_id' => null,
            'customer_name' => 'Walk-in Customer',
            'cashier_id' => $cashier->id,
            'cashier_name' => $cashier->name,
            'tenant_id' => $tenant->id,
            'store_name' => 'Main Store',
            'status' => 'completed',
        ]);

        // Transaction 3: Mobile payment
        $items3 = [
            [
                'product_id' => $products[3]->id,
                'name' => $products[3]->name,
                'sku' => $products[3]->sku,
                'quantity' => 1,
                'price' => $products[3]->price,
                'total' => $products[3]->price,
            ],
            [
                'product_id' => $products[4]->id,
                'name' => $products[4]->name,
                'sku' => $products[4]->sku,
                'quantity' => 2,
                'price' => $products[4]->price,
                'total' => $products[4]->price * 2,
            ],
        ];

        $subtotal3 = collect($items3)->sum('total');
        $taxAmount3 = $subtotal3 * 0.10;
        $total3 = $subtotal3 + $taxAmount3;

        SalesTransaction::create([
            'transaction_date' => now()->subMinutes(30),
            'items' => $items3,
            'subtotal' => $subtotal3,
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => 10.00,
            'tax_amount' => $taxAmount3,
            'total' => $total3,
            'amount_paid' => $total3,
            'change' => 0,
            'payment_method' => 'mobile',
            'payment_details' => [
                'mobileProvider' => 'PayNow',
                'mobileNumber' => '+65-9XXX-XXXX',
                'mobileReference' => 'PAY-' . now()->format('YmdHis'),
            ],
            'customer_id' => $customer?->id,
            'customer_name' => $customer?->name ?? 'Walk-in Customer',
            'cashier_id' => $cashier->id,
            'cashier_name' => $cashier->name,
            'tenant_id' => $tenant->id,
            'store_name' => 'Main Store',
            'status' => 'completed',
        ]);

        $this->command->info('Sample sales transactions created successfully!');
    }
}
