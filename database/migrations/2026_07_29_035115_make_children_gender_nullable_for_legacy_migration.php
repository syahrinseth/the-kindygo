<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->string('gender')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The original schema only accepts male/female, so preserve its legacy fallback
        // before restoring the non-null constraint.
        DB::table('children')
            ->whereNull('gender')
            ->update(['gender' => 'male']);

        Schema::table('children', function (Blueprint $table) {
            $table->string('gender')->nullable(false)->change();
        });
    }
};
