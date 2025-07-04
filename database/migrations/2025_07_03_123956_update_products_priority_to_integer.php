<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing data to use numeric values
        DB::table('products')->where('priority', 'critical')->update(['priority' => '4']);
        DB::table('products')->where('priority', 'high')->update(['priority' => '3']);
        DB::table('products')->where('priority', 'medium')->update(['priority' => '2']);
        DB::table('products')->where('priority', 'low')->update(['priority' => '1']);
        
        // Then change the column type to integer
        Schema::table('products', function (Blueprint $table) {
            $table->integer('priority')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change back to string
        Schema::table('products', function (Blueprint $table) {
            $table->string('priority')->change();
        });
        
        // Convert numeric values back to strings
        DB::table('products')->where('priority', '4')->update(['priority' => 'critical']);
        DB::table('products')->where('priority', '3')->update(['priority' => 'high']);
        DB::table('products')->where('priority', '2')->update(['priority' => 'medium']);
        DB::table('products')->where('priority', '1')->update(['priority' => 'low']);
    }
};
