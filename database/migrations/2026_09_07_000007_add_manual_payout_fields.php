<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->string('payout_method', 20)->nullable()->after('refund_method');
            $table->text('payout_details')->nullable()->after('payout_method');
            $table->string('payout_reference')->nullable()->after('payout_details');
        });

        Schema::table('order_refunds', function (Blueprint $table) {
            $table->string('payout_reference')->nullable()->after('refund_method');
        });
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropColumn('payout_reference');
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn(['payout_method', 'payout_details', 'payout_reference']);
        });
    }
};
