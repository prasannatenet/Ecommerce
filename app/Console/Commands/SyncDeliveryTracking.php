<?php

namespace App\Console\Commands;

use App\Models\DeliveryPartner;
use App\Models\Shipment;
use App\Services\Delivery\DeliveryManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncDeliveryTracking extends Command
{
    protected $signature = 'delivery:sync-tracking
        {--limit=50 : Maximum number of shipments to sync in one run}
        {--partner= : Only sync shipments for this delivery partner id}';

    protected $description = 'Sync tracking for active shipments from configured delivery partners';

    public function handle(DeliveryManager $manager): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1) {
            $this->error('Limit must be at least 1.');
            return self::FAILURE;
        }

        $partnerId = $this->option('partner');

        $partners = DeliveryPartner::query()
            ->where('is_active', true)
            ->where('driver', '!=', 'manual')
            ->where(function ($query) {
                $query->whereNotNull('api_key')->orWhere('use_b2c_one', true);
            })
            ->when($partnerId, fn ($query) => $query->whereKey($partnerId))
            ->get();

        if ($partners->isEmpty()) {
            $this->info('No active delivery partners found.');
            return self::SUCCESS;
        }

        // Rule: "Automatic tracking updates" - the scheduled run only touches
        // partners that have the rule enabled. An explicit --partner request
        // (the admin "Sync now" button) always runs.
        if (! $partnerId) {
            $partners = $partners->filter(fn (DeliveryPartner $partner) => (bool) $partner->auto_sync_tracking);

            if ($partners->isEmpty()) {
                $this->info('Automatic tracking updates are disabled for all delivery partners.');
                return self::SUCCESS;
            }
        }

        $count = 0;
        $errors = 0;

        foreach ($partners as $partner) {
            $shipments = Shipment::where('delivery_partner_id', $partner->id)
                ->whereNotNull('tracking_number')
                ->whereNotIn('status', [
                    'delivered',
                    'cancelled',
                    'rto',
                    'booking_failed',
                ])
                ->where(function ($query) {
                    $query->whereNull('last_synced_at')
                        ->orWhere('last_synced_at', '<', now()->subMinutes(5));
                })
                ->orderBy('last_synced_at')
                ->limit($limit)
                ->get();

            if ($shipments->isEmpty()) {
                continue;
            }

            $this->line(sprintf(
                'Syncing %d shipments for %s...',
                $shipments->count(),
                $partner->name
            ));

            foreach ($shipments as $shipment) {
                try {
                    $ok = $manager->sync($shipment);

                    if ($ok) {
                        $count++;
                        $this->line(sprintf(
                            '  [OK] Shipment #%d -> %s (%s)',
                            $shipment->id,
                            $shipment->fresh()->status,
                            $shipment->tracking_number
                        ));
                    } else {
                        $errors++;
                        $this->warn(sprintf(
                            '  [WARN] Shipment #%d - %s',
                            $shipment->id,
                            $shipment->last_sync_error ?? 'no events returned'
                        ));
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error('Delivery sync command failed for shipment', [
                        'shipment' => $shipment->id,
                        'partner' => $partner->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error(sprintf(
                        '  [ERR] Shipment #%d - %s',
                        $shipment->id,
                        $e->getMessage()
                    ));
                }
            }
        }

        $this->info(sprintf(
            'Done. Synced: %d | Errors: %d',
            $count,
            $errors
        ));

        return self::SUCCESS;
    }
}