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
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('patronymic')->nullable();
            $table->string('mykid_no')->nullable();
            $table->date('date_of_birth');
            $table->string('place_of_birth')->nullable();
            $table->string('gender');
            $table->string('cert_number')->nullable();
            $table->integer('position_of_child')->nullable();
            $table->string('race')->nullable();
            $table->string('religion')->nullable();
            $table->string('languages')->nullable();

            $table->string('allergies')->nullable();
            $table->string('diseases')->nullable();
            $table->string('family_clinic')->nullable();
            $table->string('family_clinic_phone')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
