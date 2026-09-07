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
        Schema::create('product_variations', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('product_id');
    $table->string('sku')->unique();
    $table->decimal('price', 10, 2);
    $table->integer('stock')->default(0);
    $table->json('attributes')->nullable(); // e.g. {"size":"M","color":"Red"}
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
