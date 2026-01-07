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
        Schema::table('child_enrolments', function (Blueprint $table) {
            $table->dateTime('next_bill_date')->nullable()->after('date_end');

            // Add compound indexes for efficient querying
            $table->index(['tenant_id', 'next_bill_date']);
            $table->index(['billed_every', 'next_bill_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('child_enrolments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'next_bill_date']);
            $table->dropIndex(['billed_every', 'next_bill_date']);
            $table->dropColumn('next_bill_date');
        });
    }
};
