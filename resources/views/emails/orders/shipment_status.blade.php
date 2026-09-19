<p>Hello {{ optional($order?->user)->name ?? 'Customer' }},</p>

<p>Your order <strong>#{{ $order?->id }}</strong> has a delivery update:</p>

<p>
    Status: <strong>{{ $statusLabel }}</strong><br>
    @if($shipment?->deliveryPartner)
        Delivery Partner: {{ $shipment->deliveryPartner->name }}<br>
    @endif
    @if($shipment?->tracking_number)
        AWB / Tracking Number: <strong>{{ $shipment->tracking_number }}</strong><br>
    @endif
    @if($shipment?->delivered_at)
        Delivered On: {{ $shipment->delivered_at->format('M d, Y h:i A') }}<br>
    @elseif($shipment?->shipped_at)
        Shipped On: {{ $shipment->shipped_at->format('M d, Y h:i A') }}<br>
    @endif
</p>

@if($shipment?->trackingEvents->isNotEmpty())
    <p>Latest tracking activity:</p>
    <ul>
        @foreach($shipment->trackingEvents->take(5) as $event)
            <li>
                {{ $event->status_label ?: ucfirst(str_replace('_', ' ', $event->status_code)) }}
                @if($event->location) — {{ $event->location }}@endif
                @if($event->scanned_at) — {{ \Carbon\Carbon::parse($event->scanned_at)->format('M d, Y h:i A') }}@endif
            </li>
        @endforeach
    </ul>
@endif

@php
    $trackingUrl = $shipment?->tracking_url
        ?: ($shipment?->deliveryPartner && $shipment->tracking_number
            ? $shipment->deliveryPartner->trackingUrlFor($shipment->tracking_number)
            : null);
@endphp

@if($trackingUrl)
    <p><a href="{{ $trackingUrl }}">Track this shipment</a></p>
@endif

<p>You can also follow this order from your account order history.</p>

<p>Thank you for shopping with us.</p>