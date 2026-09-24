<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'paid_at')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->timestamp('paid_at')->nullable()->after('payment_status')->index();
            });
        }

        // Existing paid orders do not have a capture timestamp. Their last update
        // time is the closest historical value available for dashboard backfill.
        DB::table('orders')
            ->where('payment_status', 'paid')
            ->whereNull('paid_at')
            ->update(['paid_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'paid_at')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex(['paid_at']);
                $table->dropColumn('paid_at');
            });
        }
    }
};
