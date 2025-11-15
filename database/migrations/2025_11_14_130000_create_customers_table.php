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
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('customer_code')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->json('address')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->string('loyalty_tier')->default('bronze');
            $table->unsignedInteger('total_purchases')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->timestamp('last_purchase_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('loyalty_tier');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
