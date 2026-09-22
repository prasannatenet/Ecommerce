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
        Schema::table('product_variations', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variations', 'description')) {
                $table->text('description')->nullable()->after('price');
            }

            if (! Schema::hasColumn('product_variations', 'discount_type')) {
                $table->string('discount_type', 20)->nullable()->after('description');
            }

            if (! Schema::hasColumn('product_variations', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            }

            if (! Schema::hasColumn('product_variations', 'sale_price')) {
                $table->decimal('sale_price', 10, 2)->nullable()->after('discount_value');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            foreach (['sale_price', 'discount_value', 'discount_type', 'description'] as $column) {
                if (Schema::hasColumn('product_variations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
