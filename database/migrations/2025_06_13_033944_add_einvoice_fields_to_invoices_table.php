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
            $table->string('einvoice_uuid')->nullable()->index();
            $table->string('einvoice_submission_id')->nullable();
            $table->string('einvoice_status')->nullable();
            $table->text('einvoice_validation_url')->nullable();
            $table->timestamp('einvoice_submitted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'einvoice_uuid',
                'einvoice_submission_id',
                'einvoice_status',
                'einvoice_validation_url',
                'einvoice_submitted_at',
            ]);
        });
    }
};
