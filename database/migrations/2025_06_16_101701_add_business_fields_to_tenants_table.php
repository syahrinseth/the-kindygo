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
            // Business Registration Information
            $table->string('tax_identification_number')->nullable()->after('state')
                ->comment('TIN (Tax Identification Number) for e-Invoice');
            $table->string('business_registration_number')->nullable()->after('tax_identification_number')
                ->comment('Company registration number (ROC/ROB)');
            $table->string('business_activity_code')->nullable()->after('business_registration_number')
                ->comment('MSIC business activity code');
            $table->string('business_activity_description')->nullable()->after('business_activity_code')
                ->comment('Description of business activity');

            // Enhanced Address Information for e-Invoice
            $table->string('country')->default('MY')->after('business_activity_description')
                ->comment('Country code (ISO 3166-1)');
            $table->string('state_code')->nullable()->after('country')
                ->comment('State code for e-Invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'tax_identification_number',
                'business_registration_number',
                'business_activity_code',
                'business_activity_description',
                'country',
                'state_code',
            ]);
        });
    }
};
