<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'checkout_token')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->uuid('checkout_token')->nullable()->unique()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'checkout_token')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropUnique(['checkout_token']);
                $table->dropColumn('checkout_token');
            });
        }
    }
};
