<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_loyalty_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();
            $table->enum('type', ['earned', 'redeemed', 'adjusted']);
            $table->integer('points_delta');
            $table->integer('points_balance');
            $table->decimal('total_spent_delta', 12, 2)->default(0);
            $table->decimal('total_spent_balance', 12, 2)->default(0);
            $table->integer('purchases_delta')->default(0);
            $table->integer('purchases_balance')->default(0);
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_transactions');
    }
};
