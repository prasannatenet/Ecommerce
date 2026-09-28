<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\Delivery\DeliveryStatus;
use Illuminate\Console\Command;

/**
 * One-off repair for shipments left in an inconsistent state.
 *
 * A shipment can end up marked "booked" while holding no AWB when a booking
 * wrote the status before the waybill step completed (or a partial failure left
 * it half written). It is invisible in the UI, but it makes the booked/shipped
 * numbers on the dashboard lie, and those rows can never be tracked.
 */
class RepairShipmentConsistency extends Command
{
    protected $signature = 'delivery:repair-shipments
        {--dry-run : Report what would change without writing}';

    protected $description = 'Reset shipments that claim to be booked or shipped but have no AWB';

    /**
     * Statuses that can only have come from a courier, never from a human.
     *
     * "booked" means a waybill was allocated, so a row sitting in this state
     * with no AWB is genuinely corrupt. The other states are deliberately
     * excluded: an operator can legitimately mark a parcel delivered by hand
     * (self pickup, hand delivery, courier booked on another platform) without
     * an AWB ever existing in this table, and rewriting that to "pending" would
     * tell the customer their delivered parcel is back in the queue.
     */
    private const REPAIRABLE_STATUSES = [
        DeliveryStatus::BOOKED,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = fn () => Shipment::query()
            ->whereNull('tracking_number')
            ->whereIn('status', self::REPAIRABLE_STATUSES);

        $broken = $query()->get(['id', 'order_id', 'status', 'last_sync_error']);

        if ($broken->isEmpty()) {
            $this->info('No inconsistent shipments found.');

            return self::SUCCESS;
        }

        foreach ($broken as $shipment) {
            $this->line(sprintf(
                '  #%d (order %d): %s -> %s%s',
                $shipment->id,
                $shipment->order_id,
                $shipment->status,
                DeliveryStatus::PENDING,
                $shipment->last_sync_error ? ' | ' . \Illuminate\Support\Str::limit($shipment->last_sync_error, 70) : ''
            ));
        }

        $this->newLine();
        $this->info(sprintf('%d shipment(s) claim to be booked but have no AWB.', $broken->count()));

        if ($dryRun) {
            $this->comment('Dry run: nothing was changed. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        $updated = $query()->update(['status' => DeliveryStatus::PENDING]);

        $this->info("Reset {$updated} shipment(s) to pending. Re-book them from the order screen once the courier token is saved.");

        return self::SUCCESS;
    }
}
