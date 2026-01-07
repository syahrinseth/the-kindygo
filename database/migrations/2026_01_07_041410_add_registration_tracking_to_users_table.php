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
            $table->integer('registration_step')->default(0)->nullable()->after('profile_completed');
            $table->json('registration_data')->nullable()->after('registration_step');
            $table->string('registration_token')->nullable()->index()->after('registration_data');
            $table->timestamp('registration_token_expires_at')->nullable()->after('registration_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'registration_step',
                'registration_data',
                'registration_token',
                'registration_token_expires_at',
            ]);
        });
    }
};
