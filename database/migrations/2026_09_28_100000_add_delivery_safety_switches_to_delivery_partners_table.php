<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the operational switches the delivery panel needs to be safe in
     * production:
     *
     * - require_webhook_signature: reject unsigned courier callbacks instead of
     *   trusting them. Without it anyone could POST a fake "Delivered" event.
     * - auto_cancel_with_order: call the courier's cancellation API when an
     *   order is cancelled locally, so Delhivery does not still ship/pick it up.
     */
    public function up(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_partners', 'require_webhook_signature')) {
                $table->boolean('require_webhook_signature')->default(false)->after('webhook_secret');
            }
            if (! Schema::hasColumn('delivery_partners', 'auto_cancel_with_order')) {
                $table->boolean('auto_cancel_with_order')->default(true)->after('auto_book_on');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->dropColumn(['require_webhook_signature', 'auto_cancel_with_order']);
        });
    }
};
