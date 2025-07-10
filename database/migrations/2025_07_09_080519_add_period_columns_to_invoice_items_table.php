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
        Schema::table('invoice_items', function (Blueprint $table) {
            // Add columns that might be missing
            if (!Schema::hasColumn('invoice_items', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('invoice_items', 'period_start')) {
                $table->datetime('period_start')->nullable()->after('total');
            }
            if (!Schema::hasColumn('invoice_items', 'period_end')) {
                $table->datetime('period_end')->nullable()->after('period_start');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $columns = ['description', 'period_start', 'period_end'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
