<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE coupons MODIFY type ENUM('percent', 'fixed', 'buy_get', 'gehna_coins') NOT NULL DEFAULT 'percent'");
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('buy_quantity')->nullable()->after('amount');
            $table->unsignedInteger('get_quantity')->nullable()->after('buy_quantity');
            $table->unsignedInteger('reward_coins')->nullable()->after('get_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['buy_quantity', 'get_quantity', 'reward_coins']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE coupons MODIFY type ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent'");
        }
    }
};