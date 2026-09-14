<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order;
use App\Models\DeliveryPartner;

class Shipment extends Model
{
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
}
