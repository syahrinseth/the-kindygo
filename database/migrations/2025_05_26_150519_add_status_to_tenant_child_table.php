<?php

use App\Enums\ChildStatus;
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
        Schema::table('tenant_child', function (Blueprint $table) {
            $table->string('status')->default(ChildStatus::NEW->value)->after('child_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_child', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
