<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'discount_type')) {
                $table->string('discount_type', 20)->nullable()->after('sale_price');
            }

            if (! Schema::hasColumn('products', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'discount_value')) {
                $table->dropColumn('discount_value');
            }

            if (Schema::hasColumn('products', 'discount_type')) {
                $table->dropColumn('discount_type');
            }
        });
    }
};
