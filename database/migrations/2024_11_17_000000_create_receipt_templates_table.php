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
        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('paper_width')->default(80); // mm
            $table->boolean('is_default')->default(false);

            // Sections configuration stored as JSON
            $table->json('sections')->nullable();

            // Styles configuration stored as JSON
            $table->json('styles')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Ensure only one default template
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_templates');
    }
};
