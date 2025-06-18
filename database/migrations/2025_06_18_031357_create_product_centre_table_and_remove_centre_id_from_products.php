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
        // First, create the product_centre pivot table
        Schema::create('product_centre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('centre_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Add unique constraint to prevent duplicate relationships
            $table->unique(['product_id', 'centre_id']);
            
            // Add indexes for better performance
            $table->index('product_id');
            $table->index('centre_id');
        });
        
        // Migrate existing data from products.centre_id to product_centre pivot table
        DB::statement('
            INSERT INTO product_centre (product_id, centre_id, created_at, updated_at)
            SELECT id, centre_id, created_at, updated_at
            FROM products 
            WHERE centre_id IS NOT NULL
        ');
        
        // Remove the centre_id column from products table
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['centre_id']);
            $table->dropColumn('centre_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the centre_id column to products table
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('centre_id')->nullable()->constrained()->onDelete('set null');
        });
        
        // Migrate data back from pivot table to products.centre_id
        // Note: This will only work if each product has at most one centre
        // If a product has multiple centres, only the first one will be migrated back
        DB::statement('
            UPDATE products 
            SET centre_id = (
                SELECT centre_id 
                FROM product_centre 
                WHERE product_centre.product_id = products.id 
                LIMIT 1
            )
        ');
        
        // Drop the product_centre pivot table
        Schema::dropIfExists('product_centre');
    }
};
