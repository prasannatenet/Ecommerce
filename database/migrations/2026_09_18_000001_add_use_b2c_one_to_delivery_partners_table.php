<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_partners', 'use_b2c_one')) {
                $table->boolean('use_b2c_one')->default(false)->after('is_sandbox');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->dropColumn('use_b2c_one');
        });
    }
};
