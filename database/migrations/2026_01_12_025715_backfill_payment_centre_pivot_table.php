<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Copy existing payment → centre relationships to pivot table
        // Only backfill payments that have a centre_id set
        DB::statement('
            INSERT INTO payment_centre (payment_id, centre_id, allocated_amount, created_at, updated_at)
            SELECT id, centre_id, amount, created_at, updated_at
            FROM payments
            WHERE centre_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore centre_id from payment_centre pivot (take first centre if multiple)
        DB::statement('
            UPDATE payments p
            INNER JOIN (
                SELECT payment_id, MIN(centre_id) as centre_id
                FROM payment_centre
                GROUP BY payment_id
            ) pc ON p.id = pc.payment_id
            SET p.centre_id = pc.centre_id
            WHERE p.centre_id IS NULL
        ');

        // Clear the pivot table
        DB::table('payment_centre')->truncate();
    }
};
