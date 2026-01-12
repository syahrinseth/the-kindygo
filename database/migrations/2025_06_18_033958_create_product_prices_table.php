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
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('price'); // Price in cents
            $table->date('start_date');
            $table->date('end_date')->nullable(); // Nullable for open-ended prices
            $table->timestamps();

            // Add indexes for better performance
            $table->index('product_id');
            $table->index('start_date');
            $table->index('end_date');

            // Add index for date range queries
            $table->index(['product_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
