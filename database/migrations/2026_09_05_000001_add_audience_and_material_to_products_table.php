<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'audience')) {
                $table->string('audience', 20)->default('unisex')->after('name');
            }

            if (! Schema::hasColumn('products', 'material_type')) {
                $table->string('material_type', 30)->default('other')->after('audience');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'material_type')) {
                $table->dropColumn('material_type');
            }

            if (Schema::hasColumn('products', 'audience')) {
                $table->dropColumn('audience');
            }
        });
    }
};
