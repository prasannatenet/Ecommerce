<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Order;
use App\Models\DeliveryPartner;
use App\Services\Delivery\DeliveryStatus;

class Shipment extends Model
{
    /**
     * Statuses treated as "in transit" on the shipment dashboard.
     */
    public const IN_TRANSIT_STATUSES = [
        DeliveryStatus::SHIPPED,
        DeliveryStatus::IN_TRANSIT,
        DeliveryStatus::OUT_FOR_DELIVERY,
    ];

    /**
     * Statuses treated as delivery exceptions on the shipment dashboard.
     */
    public const EXCEPTION_STATUSES = [
        DeliveryStatus::UNDELIVERED,
        DeliveryStatus::RTO,
        DeliveryStatus::CANCELLED,
        'booking_failed',
    ];

    protected $fillable = [
        'order_id',
        'delivery_partner_id',
        'tracking_number',
        'tracking_url',
        'provider_shipment_id',
        'label_url',
        'status',
        'shipped_at',
        'delivered_at',
        'booking_requested_at',
        'booked_at',
        'last_synced_at',
        'last_sync_error',
        'meta',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'booking_requested_at' => 'datetime',
        'booked_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'meta' => 'array',
    ];

    public function trackingEvents()
    {
        return $this->hasMany(ShipmentTrackingEvent::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryPartner()
    {
        return $this->belongsTo(DeliveryPartner::class);
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->whereIn('status', self::IN_TRANSIT_STATUSES);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', DeliveryStatus::DELIVERED);
    }

    /**
     * A shipment is an exception when the courier reported a failed delivery,
     * an RTO/cancellation, a failed booking, or when the last sync errored.
     */
    public function scopeExceptions(Builder $query): Builder
    {
        return $query->where(function (Builder $inner) {
            $inner->whereIn('status', self::EXCEPTION_STATUSES)
                ->orWhereNotNull('last_sync_error');
        });
    }

    public function statusLabel(): string
    {
        $labels = DeliveryStatus::labels();

        return $labels[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /**
     * True once Delhivery has pushed/returned real tracking data for this
     * shipment, so the UI can show a "Synced from provider" badge.
     */
    public function isSyncedFromProvider(): bool
    {
        return $this->last_synced_at !== null && $this->last_sync_error === null;
    }

    public function isException(): bool
    {
        return in_array($this->status, self::EXCEPTION_STATUSES, true) || $this->last_sync_error !== null;
    }
}
