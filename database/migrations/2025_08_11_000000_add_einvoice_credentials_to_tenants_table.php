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
            // Add e-Invoice API credentials specific to each tenant
            $table->string('einvoice_client_id')->nullable()->after('state_code')
                ->comment('LHDN MyInvois Client ID for this tenant');
            $table->string('einvoice_client_secret')->nullable()->after('einvoice_client_id')
                ->comment('LHDN MyInvois Client Secret for this tenant');
            $table->string('einvoice_environment')->default('sandbox')->after('einvoice_client_secret')
                ->comment('Environment: sandbox or production');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'einvoice_client_id',
                'einvoice_client_secret',
                'einvoice_environment',
            ]);
        });
    }
};
