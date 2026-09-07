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
        Schema::table('carts', function (Blueprint $table) {
            // Drop current index/foreign key first to alter
            $table->dropForeign(['product_variation_id']);
            
            // Alter product_variation_id to be nullable
            $table->unsignedBigInteger('product_variation_id')->nullable()->change();
            
            // Add product_id to link explicitly with the root product
            $table->unsignedBigInteger('product_id')->nullable()->after('user_id');
            
            // Reapply constraints
            $table->foreign('product_variation_id')->references('id')->on('product_variations')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            
            $table->dropForeign(['product_variation_id']);
            $table->unsignedBigInteger('product_variation_id')->nullable(false)->change();
            $table->foreign('product_variation_id')->references('id')->on('product_variations')->onDelete('cascade');
        });
    }
};
