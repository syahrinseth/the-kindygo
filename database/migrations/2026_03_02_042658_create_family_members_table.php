<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create family_members table for storing spouse and family data.
     */
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('relationship_type')->default('spouse');
            $table->string('name')->nullable();
            $table->string('nric')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('occupation')->nullable();

            // Personal address
            $table->text('address')->nullable();
            $table->text('address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('state_code')->nullable();

            // Office address
            $table->string('office_name')->nullable();
            $table->text('office_address')->nullable();
            $table->text('office_address_2')->nullable();
            $table->string('office_city')->nullable();
            $table->string('office_postal_code')->nullable();
            $table->string('office_state_code')->nullable();

            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
