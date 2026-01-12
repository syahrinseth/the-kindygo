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
        Schema::table('users', function (Blueprint $table) {
            // Remove fields that are now in separate tables
            $table->dropColumn([
                'phone',
                'nric',
                'passport',
                'city',
                'postal_code',
                'state_code',
                'address',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Re-add the removed fields
            $table->string('phone')->nullable();
            $table->string('nric')->nullable()->comment('Malaysian NRIC/IC number for individual customers');
            $table->string('passport')->nullable()->comment('Passport number for foreign customers');
            $table->string('city')->nullable()->comment('Customer city for e-invoice address');
            $table->string('postal_code')->nullable()->comment('Customer postal code for e-invoice address');
            $table->string('state_code')->nullable()->comment('Malaysian state code for e-invoice (e.g., 14 for KL)');
            $table->text('address')->nullable()->comment('Customer full address for e-invoice');
        });
    }
};
