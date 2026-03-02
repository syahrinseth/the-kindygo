<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make invoice columns nullable to accommodate legacy data where
     * due_date, centre_id, and user_id may be null.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->datetime('due_at')->nullable()->change();
            $table->unsignedBigInteger('centre_id')->nullable()->change();
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->datetime('due_at')->nullable(false)->change();
            $table->unsignedBigInteger('centre_id')->nullable(false)->change();
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
