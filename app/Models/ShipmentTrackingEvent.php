<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentTrackingEvent extends Model
{
    protected $fillable = [
        'shipment_id',
        'provider_event_id',
        'status_code',
        'status_label',
        'location',
        'remarks',
        'scanned_at',
        'raw_payload',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
