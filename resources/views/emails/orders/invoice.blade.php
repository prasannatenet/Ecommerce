@extends('emails.layout')

@section('title', 'Invoice INV-' . $order->id)
@section('header_note', 'Invoice')
@section('preheader', 'Invoice INV-' . $order->id . ' for Rs ' . number_format((float) $order->total, 2) . ' is attached to this email as a PDF.')
@section('eyebrow', 'Order #' . $order->id)
@section('heading', 'Your invoice is attached')

@section('content')
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#EFF5F1;border:1px solid #DCE7E1;border-radius:14px;margin:0 0 20px;">
        <tr>
            <td style="padding:16px 20px;font-size:15px;line-height:23px;color:#0F3D32;">
                <div style="font-size:15px;">Hello <strong>{{ optional($order->user)->name ?? 'Customer' }}</strong>, &#128075;</div>
                <div style="margin-top:6px;font-size:14px;line-height:22px;color:#3E5A51;">Thank you for your order. Invoice <strong style="color:#0F3D32;">INV-{{ $order->id }}</strong> is attached to this email as a PDF, and a copy is always available from your account.</div>
            </td>
        </tr>
    </table>

    @include('emails.partials.detail-rows', ['title' => 'Invoice summary', 'rows' => [
        ['label' => 'Order No', 'value' => '#' . $order->id],
        ['label' => 'Order Date', 'value' => optional($order->created_at)->format('d M Y, h:i A')],
        ['label' => 'Payment Method', 'value' => strtoupper($order->payment_method ?? 'N/A')],
        ['label' => 'Payment Status', 'value' => strtoupper($order->payment_status ?? 'PENDING')],
        ['label' => 'Order Total', 'value' => 'Rs ' . number_format((float) $order->total, 2), 'strong' => true],
    ]])

    @if ($order->items->isNotEmpty())
        <p style="margin:26px 0 10px;font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#B08D3C;font-weight:bold;">&#10022;&nbsp;&nbsp;Items in this order</p>

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#FFFDF8;border:1px solid #E7DCC2;border-radius:14px;border-collapse:separate;overflow:hidden;">
            <tr>
                <td colspan="3" style="background-color:#0F3D32;padding:11px 18px;font-size:11px;font-weight:bold;letter-spacing:1.8px;text-transform:uppercase;color:#E8D9AE;">Order contents<span style="color:#B08D3C;">&nbsp;&nbsp;&#10022;</span></td>
            </tr>
            <tr>
                <td style="padding:10px 0 8px 18px;font-size:11px;letter-spacing:0.8px;text-transform:uppercase;color:#8A8377;font-weight:normal;border-bottom:1px solid #EDE6D8;">Item</td>
                <td align="center" width="46" style="padding:10px 0 8px;font-size:11px;letter-spacing:0.8px;text-transform:uppercase;color:#8A8377;font-weight:normal;border-bottom:1px solid #EDE6D8;">Qty</td>
                <td align="right" width="110" style="padding:10px 18px 8px 0;font-size:11px;letter-spacing:0.8px;text-transform:uppercase;color:#8A8377;font-weight:normal;border-bottom:1px solid #EDE6D8;">Amount</td>
            </tr>
            @foreach ($order->items as $item)
                <tr>
                    <td style="padding:11px 0 11px 18px;font-size:14px;line-height:20px;color:#24211D;{{ $loop->last ? '' : 'border-bottom:1px solid #F4EFE4;' }}">
                        <span style="display:inline-block;background-color:#0F3D32;color:#E8D9AE;font-size:11px;font-weight:bold;width:22px;height:22px;line-height:22px;text-align:center;border-radius:50%;margin-right:8px;vertical-align:middle;">{{ $loop->iteration }}</span>{{ $item->product_name }}
                        @if ($item->sku)
                            <div style="font-size:12px;color:#8A8377;margin-top:2px;">SKU: {{ $item->sku }}</div>
                        @endif
                    </td>
                    <td align="center" style="padding:11px 0;font-size:14px;font-weight:bold;color:#0F3D32;{{ $loop->last ? '' : 'border-bottom:1px solid #F4EFE4;' }}">&times; {{ $item->quantity }}</td>
                    <td align="right" style="padding:11px 18px 11px 0;font-size:14px;font-weight:bold;color:#0F3D32;{{ $loop->last ? '' : 'border-bottom:1px solid #F4EFE4;' }}">Rs {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" style="background-color:#FBF3E2;border-top:1px solid #E7DCC2;padding:13px 18px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td align="left" style="font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#8A6A1F;font-weight:bold;">&#10022;&nbsp; Order Total</td>
                            <td align="right" style="font-size:19px;font-weight:bold;color:#0F3D32;">Rs {{ number_format((float) $order->total, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endif
@endsection

@section('cta')
    @include('emails.partials.button', ['url' => route('orders.show', $order), 'label' => 'View your order'])
@endsection

@section('footer_note')
    Please keep invoice INV-{{ $order->id }} for your records. For any billing question, simply reply to this email.
@endsection

