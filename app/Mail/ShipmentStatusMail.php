<?php

namespace App\Mail;

use App\Models\Shipment;
use App\Services\Delivery\DeliveryStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShipmentStatusMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Shipment $shipment,
        public string $shipmentStatus
    ) {
    }

    public function build(): self
    {
        $labels = DeliveryStatus::labels();
        $statusLabel = $labels[$this->shipmentStatus] ?? ucfirst(str_replace('_', ' ', $this->shipmentStatus));

        return $this->subject('Order #' . $this->shipment->order_id . ' — ' . $statusLabel)
            ->view('emails.orders.shipment_status', [
                'shipment' => $this->shipment,
                'order' => $this->shipment->order,
                'statusLabel' => $statusLabel,
            ]);
    }
}
