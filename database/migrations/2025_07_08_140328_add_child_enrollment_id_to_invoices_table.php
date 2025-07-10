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
            $table->foreignId('child_enrollment_id')->nullable()->after('user_id')->constrained('child_enrollments')->onDelete('cascade');
            $table->foreignId('child_id')->nullable()->after('child_enrollment_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['child_enrollment_id']);
            $table->dropColumn('child_enrollment_id');
            $table->dropForeign(['child_id']);
            $table->dropColumn('child_id');
        });
    }
};
