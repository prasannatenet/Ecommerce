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
        if (Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_provider_id')->nullable()->constrained('payment_providers')->nullOnDelete();
            $table->string('type', 30)->default('payment');
            $table->string('status', 30)->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('INR');
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_refund_id')->nullable()->index();
            $table->string('signature')->nullable();
            $table->json('payload')->nullable();
            $table->json('notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
