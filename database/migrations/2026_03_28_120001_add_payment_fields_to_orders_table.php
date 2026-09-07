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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('payment_provider_id')->nullable()->after('user_id')->constrained('payment_providers')->nullOnDelete();
            $table->string('payment_method', 50)->nullable()->after('status');
            $table->string('payment_status', 50)->default('pending')->after('payment_method');
            $table->string('transaction_id')->nullable()->after('payment_status');
            $table->string('gateway_order_id')->nullable()->after('transaction_id');
            $table->json('payment_meta')->nullable()->after('gateway_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_provider_id');
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'transaction_id',
                'gateway_order_id',
                'payment_meta',
            ]);
        });
    }
};
