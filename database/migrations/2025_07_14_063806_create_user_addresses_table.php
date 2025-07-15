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
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('address')->nullable()->comment('Full street address for e-Invoice');
            $table->text('address_2')->nullable()->comment('Additional address information (optional)');
            $table->string('city')->nullable()->comment('Customer city for e-invoice address');
            $table->string('postal_code')->nullable()->comment('Customer postal code for e-invoice address');
            $table->string('state_code')->nullable()->comment('Malaysian state code for e-invoice (e.g., 14 for KL)');
            $table->timestamps();
            
            // Add unique constraint to ensure one address per user
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
