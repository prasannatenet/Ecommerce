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
        if (! Schema::hasTable('wishlists')) {
            return;
        }

        Schema::table('wishlists', function (Blueprint $table) {
            if (! Schema::hasColumn('wishlists', 'product_variation_id')) {
                $table->unsignedBigInteger('product_variation_id')->nullable()->after('product_id');
                $table->foreign('product_variation_id')
                    ->references('id')
                    ->on('product_variations')
                    ->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('wishlists')) {
            return;
        }

        Schema::table('wishlists', function (Blueprint $table) {
            if (Schema::hasColumn('wishlists', 'product_variation_id')) {
                $table->dropForeign(['product_variation_id']);
                $table->dropColumn('product_variation_id');
            }
        });
    }
};
