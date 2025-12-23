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
            $table->foreignId('centre_id')->after('tenant_id')->constrained('centres')->onDelete('cascade');
            $table->index(['centre_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('child_enrolments', function (Blueprint $table) {
            $table->dropForeign(['centre_id']);
            $table->dropIndex(['centre_id', 'status']);
            $table->dropColumn('centre_id');
        });
    }
};
