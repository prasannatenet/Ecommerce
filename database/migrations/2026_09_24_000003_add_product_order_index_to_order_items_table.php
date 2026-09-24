<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items') || ! Schema::hasColumn('order_items', 'product_id')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->index(
                ['product_id', 'order_id'],
                'order_items_product_id_order_id_index'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_product_id_order_id_index');
        });
    }
};
