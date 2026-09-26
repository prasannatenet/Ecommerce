<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which customer redeemed which coupon.
     *
     * coupons.used_count only tracks the global limit, so without this table a
     * single account could burn the same code on order after order.
     */
    public function up(): void
    {
        Schema::create('coupon_uses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // One redemption per account. The database enforces it as well as
            // the service, so two concurrent checkouts cannot both slip past a
            // check-then-insert race.
            $table->unique(['coupon_id', 'user_id']);

            // The coupon list is filtered by customer, which the unique index
            // above (coupon_id first) cannot serve.
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_uses');
    }
};
