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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('centre_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('gateway');
            $table->string('type');
            $table->string('reference_no');
            $table->string('status');
            $table->integer('amount');
            $table->text('description')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('invoice_payment', function (Blueprint $table) {
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->timestamps();

            $table->primary(['payment_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payment');
        Schema::dropIfExists('payments');
    }
};
