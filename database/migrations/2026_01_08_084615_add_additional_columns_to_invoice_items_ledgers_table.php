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
        Schema::table('invoice_items_ledgers', function (Blueprint $table) {
            // Add user_id (parent/payer) after tenant_id
            $table->foreignId('user_id')->after('tenant_id')
                ->comment('Parent/payer user ID from invoice->user_id')
                ->constrained('users')->cascadeOnDelete();

            // Add centre_id after user_id
            $table->foreignId('centre_id')->nullable()->after('user_id')
                ->comment('Centre from invoice->centre_id')
                ->constrained('centres')->nullOnDelete();

            // Add paid boolean after balance_amount
            $table->boolean('paid')->default(false)->after('balance_amount')
                ->comment('Is this item fully paid?');

            // Add priority after paid
            $table->unsignedTinyInteger('priority')->nullable()->after('paid')
                ->comment('Payment allocation priority from product: 1=LOW, 2=MEDIUM, 3=HIGH, 4=CRITICAL');

            // Add indexes for performance
            $table->index(['user_id', 'paid', 'balance_amount'], 'idx_user_unpaid_balance');
            $table->index(['invoice_id', 'priority', 'balance_amount'], 'idx_invoice_priority_balance');
            $table->index(['invoice_item_id', 'recorded_at'], 'idx_item_recorded');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items_ledgers', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_user_unpaid_balance');
            $table->dropIndex('idx_invoice_priority_balance');
            $table->dropIndex('idx_item_recorded');

            // Drop columns
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropForeign(['centre_id']);
            $table->dropColumn('centre_id');
            $table->dropColumn('paid');
            $table->dropColumn('priority');
        });
    }
};
