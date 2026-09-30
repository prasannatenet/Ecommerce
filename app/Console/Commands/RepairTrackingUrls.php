<?php

namespace App\Console\Commands;

use App\Models\DeliveryPartner;
use App\Models\Shipment;
use Illuminate\Console\Command;

/**
 * One-off repair for tracking links that point at the courier API.
 *
 * shipments.tracking_url is written once, at booking time, and
 * DeliveryManager::backfillTrackingUrl() returns early when it is already set.
 * So if an operator pasted a Delhivery *backend* endpoint into the partner's
 * "Tracking URL Template", every shipment booked from that moment on stored a
 * link to an authenticated API URL. The buyer's browser has no API key, so the
 * page renders "Login or API Key Required" instead of the tracking history.
 *
 * Nothing repairs those rows automatically, because the code cannot tell a bad
 * stored URL from a deliberate custom one. This command makes that judgement
 * and rebuilds the link from the waybill using the partner's template.
 *
 * Deliberately a command and not a migration: the rows it touches are customer
 * facing, so the operator should see the list before anything is written.
 */
class RepairTrackingUrls extends Command
{
    protected $signature = 'delivery:repair-tracking-urls
        {--dry-run : Report what would change without writing}';

    protected $description = 'Rebuild shipment tracking URLs that point at the courier API instead of a customer page';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $shipments = Shipment::query()
            ->whereNotNull('tracking_number')
            ->whereNotNull('tracking_url')
            ->with('deliveryPartner')
            ->get(['id', 'tracking_number', 'tracking_url', 'delivery_partner_id']);

        $repaired = 0;
        $flagged = [];

        foreach ($shipments as $shipment) {
            $stored = (string) $shipment->tracking_url;

            if (! DeliveryPartner::isTrackingApiEndpoint($stored)) {
                continue;
            }

            $partner = $shipment->deliveryPartner;
            $fallback = $partner && $partner->driver === 'delhivery'
                ? DeliveryPartner::DEFAULT_TRACKING_URL
                : null;

            // trackingUrlFor() already refuses an API template, so the link is
            // rebuilt from the public page even when the partner row still holds
            // the bad value. That keeps this command safe to run before the
            // template itself has been corrected.
            $rebuilt = $partner
                ? $partner->trackingUrlFor((string) $shipment->tracking_number, $fallback)
                : $fallback;

            if ($rebuilt === null || $rebuilt === '') {
                $this->warn("  #{$shipment->id}: cannot rebuild a URL (no usable template). Left as-is.");
                continue;
            }

            $repaired++;

            $this->line(sprintf(
                '  #%d: %s -> %s',
                $shipment->id,
                \Illuminate\Support\Str::limit($stored, 70),
                $rebuilt
            ));

            if (! $dryRun) {
                $shipment->forceFill(['tracking_url' => $rebuilt])->save();
            }
        }

        $this->reportBadTemplates();

        $this->newLine();

        if ($repaired === 0) {
            $this->info('No shipment tracking URLs point at the courier API.');

            return self::SUCCESS;
        }

        $this->info("{$repaired} shipment tracking URL(s) rebuilt from the waybill.");

        if ($dryRun) {
            $this->comment('Dry run: nothing was changed. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * The partner row is the root cause, so report it even though this command
     * will not silently rewrite it: a template may have been set on purpose for
     * some other flow, and clearing it is an operator decision.
     */
    private function reportBadTemplates(): void
    {
        $bad = DeliveryPartner::query()
            ->get(['id', 'name', 'tracking_url_template'])
            ->filter(fn (DeliveryPartner $partner) => DeliveryPartner::isTrackingApiEndpoint($partner->tracking_url_template));

        if ($bad->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->warn('Delivery partner templates that point at the courier API (fix these in the admin form):');

        foreach ($bad as $partner) {
            $this->line(sprintf(
                '  #%d %s: %s',
                $partner->id,
                $partner->name,
                \Illuminate\Support\Str::limit((string) $partner->tracking_url_template, 70)
            ));
        }

        $this->comment('Set these to ' . DeliveryPartner::DEFAULT_TRACKING_URL . ' or clear them to use the default.');
    }
}