<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create tables for tracking legacy data migration progress and orphaned records.
     */
    public function up(): void
    {
        Schema::create('migration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phase');
            $table->string('source_table');
            $table->string('target_table');
            $table->integer('total_source')->default(0);
            $table->integer('total_migrated')->default(0);
            $table->integer('total_skipped')->default(0);
            $table->integer('total_errors')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_orphans', function (Blueprint $table) {
            $table->id();
            $table->string('source_table');
            $table->unsignedBigInteger('source_id');
            $table->string('reason');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['source_table', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migration_orphans');
        Schema::dropIfExists('migration_logs');
    }
};
