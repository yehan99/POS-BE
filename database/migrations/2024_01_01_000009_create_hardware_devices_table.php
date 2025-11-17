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
        Schema::create('hardware_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['PRINTER', 'SCANNER', 'CASH_DRAWER', 'PAYMENT_TERMINAL', 'CUSTOMER_DISPLAY', 'WEIGHT_SCALE'])->default('PRINTER');
            $table->enum('connection_type', ['USB', 'NETWORK', 'BLUETOOTH', 'SERIAL', 'KEYBOARD_WEDGE'])->default('USB');
            $table->enum('status', ['CONNECTED', 'DISCONNECTED', 'ERROR', 'CONNECTING'])->default('DISCONNECTED');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('ip_address')->nullable();
            $table->integer('port')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_connected')->nullable();
            $table->text('error')->nullable();
            $table->integer('operations_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->json('config')->nullable(); // Device-specific configuration
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('type');
            $table->index('status');
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hardware_devices');
    }
};
