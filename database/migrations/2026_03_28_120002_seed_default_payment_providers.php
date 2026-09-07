<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        if (! DB::table('payment_providers')->where('slug', 'cod')->exists()) {
            DB::table('payment_providers')->insert([
                'name' => 'Cash on Delivery',
                'slug' => 'cod',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('payment_providers')->where('slug', 'razorpay')->exists()) {
            DB::table('payment_providers')->insert([
                'name' => 'Razorpay',
                'slug' => 'razorpay',
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep provider rows intact to avoid accidentally removing configured keys.
    }
};
