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
        Schema::create('payment_centre', function (Blueprint $table) {
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('centre_id')->constrained()->onDelete('cascade');
            $table->integer('allocated_amount')->default(0)->comment('Amount allocated to this centre in cents');
            $table->timestamps();

            $table->primary(['payment_id', 'centre_id']);
            $table->index('centre_id');
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_centre');
    }
};
