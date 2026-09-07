<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add WordPress/WooCommerce-style features:
     * - Tags table
     * - Product-Tag pivot
     * - Product type (simple/variable), sku, stock, sale_price, manage_stock, weight
     * - Category image
     */
    public function up(): void
    {
        // 1. Tags table (like WordPress tags)
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Product-Tag pivot (many-to-many)
        Schema::create('product_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['product_id', 'tag_id']);
        });

        // 3. Enhance products table with WooCommerce-style fields
        Schema::table('products', function (Blueprint $table) {
            $table->enum('product_type', ['simple', 'variable'])->default('simple')->after('is_active');
            $table->string('sku')->nullable()->unique()->after('product_type');
            $table->integer('stock')->default(0)->after('sku');
            $table->boolean('manage_stock')->default(false)->after('stock');
            $table->decimal('sale_price', 10, 2)->nullable()->after('base_price');
            $table->decimal('weight', 8, 2)->nullable()->after('manage_stock');
            $table->string('tax_class')->nullable()->after('weight');
        });

        // 4. Add image to categories
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'sku', 'stock', 'manage_stock', 'sale_price', 'weight', 'tax_class']);
        });

        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('tags');
    }
};
