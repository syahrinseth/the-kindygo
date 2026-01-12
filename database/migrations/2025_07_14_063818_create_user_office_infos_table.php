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
        Schema::create('user_office_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('office_phone')->nullable()->comment('Office contact phone number');
            $table->text('office_address')->nullable()->comment('Office street address');
            $table->text('office_address_2')->nullable()->comment('Additional office address information (optional)');
            $table->string('office_city')->nullable()->comment('Office city');
            $table->string('office_postal_code')->nullable()->comment('Office postal code');
            $table->string('office_state_code')->nullable()->comment('Office state code');
            $table->timestamps();

            // Add unique constraint to ensure one office info per user
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_office_infos');
    }
};
