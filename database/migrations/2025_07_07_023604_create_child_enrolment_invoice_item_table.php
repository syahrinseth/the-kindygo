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
        Schema::create('child_enrolment_invoice_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_enrolment_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_item_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Ensure unique combinations
            $table->unique(['child_enrolment_id', 'invoice_item_id'], 'enrolment_invoice_item_unique');

            // Add indexes for performance
            $table->index('child_enrolment_id');
            $table->index('invoice_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_enrolment_invoice_item');
    }
};
