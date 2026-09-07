<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('order_items', 'product_variation_id')) {
            DB::statement('ALTER TABLE order_items MODIFY product_variation_id BIGINT UNSIGNED NULL');
        }

        if (Schema::hasColumn('order_items', 'price')) {
            DB::statement('ALTER TABLE order_items MODIFY price DECIMAL(10,2) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op to avoid destructive rollback on live data.
    }
};
