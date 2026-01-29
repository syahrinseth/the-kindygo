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
            // Add unique constraint: invoice numbers must be unique per tenant
            // This prevents duplicates at the database level
            $table->unique(['tenant_id', 'number', 'centre_id'], 'invoices_tenant_number_centre_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_tenant_number_centre_unique');
        });
    }
};
