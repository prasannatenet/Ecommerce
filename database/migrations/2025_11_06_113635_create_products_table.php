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
        Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('brand_id')->nullable();
    $table->unsignedBigInteger('category_id')->nullable(); // main category / final child
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('short_description')->nullable();
    $table->longText('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->decimal('base_price', 10, 2)->nullable(); // fallback price
    $table->string('primary_image')->nullable();
    $table->timestamps();

    $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
    $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
