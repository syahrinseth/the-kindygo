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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('child_id')->nullable()->constrained('children')->onDelete('set null');
            $table->string('name');
            $table->integer('price'); // Price in cents
            $table->integer('quantity')->default(1);
            $table->integer('discount')->default(0); // Discount in cents
            $table->integer('total'); // Total in cents (price * quantity - discount)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
