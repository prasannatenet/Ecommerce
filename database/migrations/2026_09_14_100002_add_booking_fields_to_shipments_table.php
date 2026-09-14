<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'provider_shipment_id')) {
                $table->string('provider_shipment_id')->nullable()->after('tracking_url');
            }
            if (! Schema::hasColumn('shipments', 'label_url')) {
                $table->string('label_url', 1000)->nullable()->after('provider_shipment_id');
            }
            if (! Schema::hasColumn('shipments', 'booking_requested_at')) {
                $table->timestamp('booking_requested_at')->nullable()->after('delivered_at');
            }
            if (! Schema::hasColumn('shipments', 'booked_at')) {
                $table->timestamp('booked_at')->nullable()->after('booking_requested_at');
            }
            if (! Schema::hasColumn('shipments', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('booked_at');
            }
            if (! Schema::hasColumn('shipments', 'last_sync_error')) {
                $table->text('last_sync_error')->nullable()->after('last_synced_at');
            }
            if (! Schema::hasColumn('shipments', 'meta')) {
                $table->json('meta')->nullable()->after('last_sync_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'provider_shipment_id', 'label_url', 'booking_requested_at',
                'booked_at', 'last_synced_at', 'last_sync_error', 'meta',
            ]);
        });
    }
};
