<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->timestamp('transaction_date');

            // Items and pricing
            $table->json('items'); // Cart items with product details
            $table->decimal('subtotal', 12, 2);
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            // Payment information
            $table->decimal('amount_paid', 12, 2);
            $table->decimal('change', 12, 2)->default(0);
            $table->enum('payment_method', ['cash', 'card', 'mobile', 'split']);
            $table->json('payment_details')->nullable();

            // Customer information
            $table->uuid('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->string('customer_name')->nullable();

            // Staff information
            $table->char('cashier_id', 26); // ULID is 26 characters
            $table->foreign('cashier_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('cashier_name');

            // Tenant/Store information
            $table->char('tenant_id', 26); // ULID is 26 characters
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('store_name')->nullable();

            // Additional information
            $table->text('notes')->nullable();
            $table->enum('status', ['completed', 'refunded', 'cancelled'])->default('completed');
            $table->string('refund_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->char('refunded_by', 26)->nullable(); // ULID is 26 characters
            $table->foreign('refunded_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for better query performance
            $table->index('transaction_number');
            $table->index('transaction_date');
            $table->index('customer_id');
            $table->index('cashier_id');
            $table->index('tenant_id');
            $table->index('status');
            $table->index(['transaction_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_transactions');
    }
};
