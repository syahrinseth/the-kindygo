<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add columns needed to store legacy data during migration.
     */
    public function up(): void
    {
        Schema::table('centres', function (Blueprint $table) {
            $table->json('meta_data')->nullable()->after('state');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('meta_data')->nullable()->after('current_tenant_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('description');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->longText('description')->nullable()->after('name');
        });

        Schema::table('user_office_infos', function (Blueprint $table) {
            $table->string('office_name')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('centres', function (Blueprint $table) {
            $table->dropColumn('meta_data');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('meta_data');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('meta');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('user_office_infos', function (Blueprint $table) {
            $table->dropColumn('office_name');
        });
    }
};
