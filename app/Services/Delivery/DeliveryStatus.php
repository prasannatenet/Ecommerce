<?php

namespace App\Services\Delivery;

final class DeliveryStatus
{
    public const PENDING = 'pending';
    public const BOOKED = 'booked';
    public const SHIPPED = 'shipped';
    public const IN_TRANSIT = 'in_transit';
    public const OUT_FOR_DELIVERY = 'out_for_delivery';
    public const DELIVERED = 'delivered';
    public const UNDELIVERED = 'undelivered';
    public const RTO = 'rto';
    public const CANCELLED = 'cancelled';

    public const DELHIVERY_DEFAULT_MAP = [
        'MANIFEST' => 'booked',
        'PICKEDUP' => 'shipped',
        'IN-TRANSIT' => 'in_transit',
        'DISPATCHED' => 'in_transit',
        'PENDING' => 'pending',
        'OUT FOR DELIVERY' => 'out_for_delivery',
        'DELIVERED' => 'delivered',
        'UNDELIVERED' => 'undelivered',
        'RTO' => 'rto',
        'CANCELLED' => 'cancelled',
        'LOST' => 'undelivered',
        'DAMAGED' => 'undelivered',
    ];

    public static function labels(): array
    {
        return [
            self::PENDING => 'Pending',
            self::BOOKED => 'Booked',
            self::SHIPPED => 'Shipped',
            self::IN_TRANSIT => 'In Transit',
            self::OUT_FOR_DELIVERY => 'Out For Delivery',
            self::DELIVERED => 'Delivered',
            self::UNDELIVERED => 'Undelivered',
            self::RTO => 'RTO',
            self::CANCELLED => 'Cancelled',
        ];
    }
}
