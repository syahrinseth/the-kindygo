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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nric')->nullable()->comment('Malaysian NRIC/IC number for individual customers');
            $table->string('passport')->nullable()->comment('Passport number for foreign customers');
            $table->string('phone')->nullable()->comment('Primary contact phone number');
            $table->string('occupation')->nullable()->comment('User occupation');
            $table->timestamps();
            
            // Add unique constraint to ensure one profile per user
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
