<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('order_id');
                $table->index(['order_id', 'product_id']);
            }

            if (! Schema::hasColumn('order_items', 'product_name')) {
                $table->string('product_name')->nullable()->after('product_variation_id');
            }

            if (! Schema::hasColumn('order_items', 'sku')) {
                $table->string('sku')->nullable()->after('product_name');
            }

            if (! Schema::hasColumn('order_items', 'unit_price')) {
                $table->decimal('unit_price', 10, 2)->nullable()->after('sku');
            }

            if (! Schema::hasColumn('order_items', 'line_total')) {
                $table->decimal('line_total', 10, 2)->nullable()->after('quantity');
            }

            if (! Schema::hasColumn('order_items', 'meta')) {
                $table->json('meta')->nullable()->after('line_total');
            }
        });

        // Backfill modern pricing columns from legacy price column where possible.
        if (Schema::hasColumn('order_items', 'price')) {
            DB::statement('UPDATE order_items SET unit_price = price WHERE unit_price IS NULL');
            DB::statement('UPDATE order_items SET line_total = price * quantity WHERE line_total IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op to avoid destructive rollback on production-like data.
    }
};
