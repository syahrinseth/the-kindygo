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
            $table->enum('type', ['product', 'invoice_discount'])->default('product')->after('total');
            $table->integer('paid_amount')->default(0)->after('type');
            $table->integer('balance_amount')->default(0)->after('paid_amount');
            $table->boolean('paid')->default(false)->after('balance_amount');
            $table->date('effective_date')->nullable()->after('paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'paid_amount',
                'balance_amount',
                'paid',
                'effective_date',
            ]);
        });
    }
};
