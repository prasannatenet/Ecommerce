<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_partners', 'auto_sync_tracking')) {
                $table->boolean('auto_sync_tracking')->default(true)->after('auto_update_order_status');
            }
            if (! Schema::hasColumn('delivery_partners', 'auto_notify_customer')) {
                $table->boolean('auto_notify_customer')->default(true)->after('auto_sync_tracking');
            }
            if (! Schema::hasColumn('delivery_partners', 'notify_events')) {
                $table->json('notify_events')->nullable()->after('auto_notify_customer');
            }
            if (! Schema::hasColumn('delivery_partners', 'client_secret')) {
                $table->text('client_secret')->nullable()->after('client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->dropColumn([
                'auto_sync_tracking', 'auto_notify_customer', 'notify_events', 'client_secret',
            ]);
        });
    }
};
