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
        Schema::create('invoice_items_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('ledger_type', ['invoice_item', 'payment_allocation'])->index();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('child_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->integer('debit_amount')->default(0)->comment('Item total or invoice amount in cents');
            $table->integer('credit_amount')->default(0)->comment('Payment allocated in cents');
            $table->integer('balance_amount')->default(0)->comment('Remaining balance in cents');
            $table->json('reference_data')->nullable()->comment('Payment metadata: gateway, reference, strategy, etc.');
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            // Composite indexes for query performance
            $table->index(['tenant_id', 'recorded_at']);
            $table->index(['tenant_id', 'invoice_id', 'recorded_at']);
            $table->index(['payment_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items_ledgers');
    }
};
