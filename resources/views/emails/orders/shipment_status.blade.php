@extends('emails.layout')

@php
    // Status colour tones: green for delivered, red for problem states, amber
    // while the parcel is moving, neutral otherwise.
    $shipmentStatusTone = match ((string) ($shipment?->status ?? '')) {
        'delivered' => ['#E8F1EC', '#0F3D32'],
        'cancelled', 'rto', 'undelivered' => ['#FBEBEB', '#A3252A'],
        'shipped', 'in_transit', 'out_for_delivery' => ['#FBF3E2', '#8A6A1F'],
        default => ['#F1EEE7', '#4A443C'],
    };

    $shipmentTrackingUrl = $shipment?->tracking_url
        ?: ($shipment?->deliveryPartner && $shipment->tracking_number
            ? $shipment->deliveryPartner->trackingUrlFor($shipment->tracking_number)
            : null);
@endphp

@section('title', 'Order #' . $order?->id . ' — ' . $statusLabel)
@section('header_note', 'Delivery Update')
@section('preheader', 'Order #' . $order?->id . ' is now ' . $statusLabel . '.')
@section('eyebrow', 'Order #' . $order?->id)
@section('heading', 'A delivery update on your order')

@section('content')
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:{{ $shipmentStatusTone[0] }};border:1px solid #E7DCC2;border-radius:14px;margin:0 0 20px;">
        <tr>
            <td style="padding:16px 20px;">
                <div style="font-size:15px;line-height:23px;color:#0F3D32;">Hello <strong>{{ optional($order?->user)->name ?? 'Customer' }}</strong>, &#128666;</div>
                <div style="margin-top:9px;">
                    <span style="display:inline-block;background-color:#0F3D32;color:#E8D9AE;font-size:12px;font-weight:bold;letter-spacing:1.2px;text-transform:uppercase;padding:8px 16px;border-radius:20px;border-bottom:2px solid #B08D3C;">{{ $statusLabel }}</span>
                </div>
                <div style="margin-top:8px;font-size:14px;line-height:22px;color:#3E5A51;">Your parcel is on its way — the latest movement is shown below.</div>
            </td>
        </tr>
    </table>

    @include('emails.partials.detail-rows', ['title' => 'Delivery summary', 'rows' => array_values(array_filter([
        ['label' => 'Order No', 'value' => '#' . $order?->id],
        $shipment?->deliveryPartner
            ? ['label' => 'Delivery Partner', 'value' => $shipment->deliveryPartner->name]
            : null,
        $shipment?->tracking_number
            ? ['label' => 'AWB / Tracking', 'value' => $shipment->tracking_number, 'strong' => true]
            : null,
        $shipment?->delivered_at
            ? ['label' => 'Delivered On', 'value' => $shipment->delivered_at->format('d M Y, h:i A')]
            : ($shipment?->shipped_at
                ? ['label' => 'Shipped On', 'value' => $shipment->shipped_at->format('d M Y, h:i A')]
                : null),
    ]))])

    @if ($shipment?->trackingEvents->isNotEmpty())
        <p style="margin:26px 0 10px;font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#B08D3C;font-weight:bold;">&#10022;&nbsp;&nbsp;Latest tracking activity</p>

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#FFFDF8;border:1px solid #E7DCC2;border-radius:14px;border-collapse:separate;overflow:hidden;">
            <tr>
                <td colspan="2" style="background-color:#0F3D32;padding:11px 18px;font-size:11px;font-weight:bold;letter-spacing:1.8px;text-transform:uppercase;color:#E8D9AE;">Tracking timeline<span style="color:#B08D3C;">&nbsp;&nbsp;&#10022;</span></td>
            </tr>
            @foreach ($shipment->trackingEvents->take(5) as $event)
                <tr>
                    <td width="30" valign="top" style="padding:13px 0 13px 18px;{{ $loop->last ? '' : 'border-bottom:1px solid #F4EFE4;' }}">
                        <div style="width:11px;height:11px;border-radius:50%;font-size:0;line-height:0;margin-top:4px;border:2px solid {{ $loop->first ? '#B08D3C' : '#D9CFB8' }};background-color:{{ $loop->first ? '#B08D3C' : '#FFFFFF' }};">&nbsp;</div>
                    </td>
                    <td style="padding:13px 18px 13px 8px;font-size:14px;line-height:20px;color:#24211D;{{ $loop->last ? '' : 'border-bottom:1px solid #F4EFE4;' }}">
                        <strong style="color:#0F3D32;">{{ $event->status_label ?: ucfirst(str_replace('_', ' ', $event->status_code)) }}</strong>
                        @if ($event->location)
                            <span style="color:#8A8377;">&mdash; {{ $event->location }}</span>
                        @endif
                        @if ($event->scanned_at)
                            <div style="font-size:12px;color:#8A8377;margin-top:2px;">
                                {{ \Carbon\Carbon::parse($event->scanned_at)->format('d M Y, h:i A') }}
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#FAF7F0;border:1px dashed #D9CFB8;border-radius:12px;margin:18px 0 0;">
        <tr>
            <td style="padding:13px 18px;font-size:13px;line-height:21px;color:#4A443C;">
                &#128269;&nbsp; You can also follow this order any time from your account order history.
            </td>
        </tr>
    </table>
@endsection

@section('cta')
    @if ($shipmentTrackingUrl)
        @include('emails.partials.button', ['url' => $shipmentTrackingUrl, 'label' => 'Track your shipment'])
    @else
        @include('emails.partials.button', ['url' => route('orders.show', $order), 'label' => 'View your order'])
    @endif
@endsection

@section('footer_note')
    Please keep your AWB number handy when contacting the delivery partner.
@endsection
