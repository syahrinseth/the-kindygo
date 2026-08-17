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
        Schema::dropIfExists('centre_child');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('centre_child', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('centre_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['centre_id', 'child_id']);
        });
    }
};
