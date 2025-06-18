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
        Schema::create('child_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->string('billed_every')->default('monthly');
            $table->datetime('date_start');
            $table->datetime('date_end')->nullable();
            $table->string('type')->default('full_time');
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index(['child_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index(['date_start', 'date_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_enrollments');
    }
};
