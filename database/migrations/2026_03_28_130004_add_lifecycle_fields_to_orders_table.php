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
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'refund_status')) {
                $table->string('refund_status', 30)->default('none')->after('payment_status');
            }

            if (! Schema::hasColumn('orders', 'refunded_total')) {
                $table->decimal('refunded_total', 10, 2)->default(0)->after('total');
            }

            if (! Schema::hasColumn('orders', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            }

            if (! Schema::hasColumn('orders', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'refund_status',
                'refunded_total',
                'cancel_reason',
                'cancelled_at',
                'refunded_at',
            ]);
        });
    }
};
