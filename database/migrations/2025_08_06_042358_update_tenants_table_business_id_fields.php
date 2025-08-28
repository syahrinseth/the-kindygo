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
        Schema::table('tenants', function (Blueprint $table) {
            // Add new business ID fields
            $table->string('business_id_type')->nullable()->after('business_activity_description')
                ->comment('Type of business identification (NRIC, BRN, PASSPORT)');
            $table->string('business_id_value', 50)->nullable()->after('business_id_type')
                ->comment('Value of the business identification');

            // Remove the old business_registration_number field
            $table->dropColumn('business_registration_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Add back the business_registration_number field
            $table->string('business_registration_number')->nullable()->after('tax_identification_number')
                ->comment('Company registration number (ROC/ROB)');

            // Remove the new business ID fields
            $table->dropColumn([
                'business_id_type',
                'business_id_value'
            ]);
        });
    }
};
