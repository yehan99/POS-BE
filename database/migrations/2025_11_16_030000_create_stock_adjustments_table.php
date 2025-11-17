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
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('adjustment_number')->unique();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('product_sku')->nullable();
            $table->foreignUuid('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('adjustment_type', 40);
            $table->integer('quantity')->default(0);
            $table->integer('quantity_change')->default(0);
            $table->integer('previous_stock')->default(0);
            $table->integer('new_stock')->default(0);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_value', 14, 2)->default(0);
            $table->decimal('value_change', 14, 2)->default(0);
            $table->string('status', 40)->default('pending');
            $table->string('created_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_by')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('location_id');
            $table->index('status');
            $table->index('adjustment_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
