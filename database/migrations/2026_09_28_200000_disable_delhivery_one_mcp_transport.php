<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Switches every delivery partner off the Delhivery One (MCP) transport.
 *
 * `use_b2c_one` selected B2CDelhiveryDriver, which calls Delhivery's MCP
 * endpoint. Delhivery documents that MCP as a read-only integration for AI
 * coding assistants (Cursor/Kiro) driven by a local `uvx` process - it is not a
 * server-to-server transport and cannot create a shipment or allocate an AWB.
 *
 * A partner left on that flag books nothing, and the failure is invisible: the
 * shipment is created, the status can be hand-edited to "Shipped", and every
 * tracking field stays empty because no waybill was ever issued.
 *
 * Booking through the Express driver is unaffected: DeliveryManager falls back
 * to it automatically for anything that needs an AWB.
 *
 * The column and the driver class are deliberately kept so this is reversible -
 * simply tick the flag back on a partner.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('delivery_partners')
            ->where('use_b2c_one', true)
            ->update([
                'use_b2c_one' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally not restored. Re-enabling this flag re-breaks booking,
        // so a rollback must not silently do it.
    }
};
