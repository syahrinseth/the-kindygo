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
        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('total_amount', 'subtotal_amount');
            $table->renameColumn('total_discounts', 'discount_amount');
            $table->renameColumn('total', 'total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('total_amount', 'invoice_total_amount');
            $table->renameColumn('subtotal_amount', 'total_amount');
            $table->renameColumn('discount_amount', 'total_discounts');
            $table->renameColumn('invoice_total_amount', 'total');
        });
    }
};
