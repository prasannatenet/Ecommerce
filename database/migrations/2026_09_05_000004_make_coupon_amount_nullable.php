<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "buy_get" and "gehna_coins" coupons do not use the amount column, so
        // it has to be nullable for those coupon types to be creatable.
        Schema::table('coupons', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable(false)->change();
        });
    }
};