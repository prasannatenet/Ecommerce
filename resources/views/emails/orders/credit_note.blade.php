@extends('emails.layout')

@section('title', 'Credit Note for Order #' . $order->id)
@section('header_note', 'Credit Note')
@section('preheader', 'A credit note of Rs ' . number_format((float) $refund->amount, 2) . ' has been issued for order #' . $order->id . '.')
@section('eyebrow', 'Order #' . $order->id)
@section('heading', 'Your credit note is attached')

@section('content')
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#EFF5F1;border:1px solid #DCE7E1;border-radius:14px;margin:0 0 20px;">
        <tr>
            <td style="padding:16px 20px;font-size:15px;line-height:23px;color:#0F3D32;">
                <div style="font-size:15px;">Hello <strong>{{ optional($order->user)->name ?? 'Customer' }}</strong>, &#129309;</div>
                <div style="margin-top:6px;font-size:14px;line-height:22px;color:#3E5A51;">A credit note has been issued for your order <strong style="color:#0F3D32;">#{{ $order->id }}</strong>. The document is attached to this email as a PDF.</div>
            </td>
        </tr>
    </table>

    @include('emails.partials.detail-rows', ['title' => 'Refund summary', 'rows' => array_values(array_filter([
        ['label' => 'Order No', 'value' => '#' . $order->id],
        ['label' => 'Refund Amount', 'value' => 'Rs ' . number_format((float) $refund->amount, 2), 'strong' => true],
        ['label' => 'Refund Status', 'value' => strtoupper($refund->status ?? 'PENDING')],
        $refund->refund_method
            ? ['label' => 'Refund Method', 'value' => strtoupper(str_replace('_', ' ', $refund->refund_method))]
            : null,
        $refund->processed_at
            ? ['label' => 'Processed On', 'value' => $refund->processed_at->format('d M Y, h:i A')]
            : null,
        ['label' => 'Reason', 'value' => $refund->reason ?: 'N/A'],
    ]))])

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#FAF7F0;border:1px dashed #D9CFB8;border-radius:12px;margin:18px 0 0;">
        <tr>
            <td style="padding:13px 18px;font-size:13px;line-height:21px;color:#4A443C;">
                &#9201;&nbsp; Depending on your bank or payment provider, the credit can take 5&ndash;7 business days to appear on your statement.
            </td>
        </tr>
    </table>
@endsection

@section('cta')
    @include('emails.partials.button', ['url' => route('orders.show', $order), 'label' => 'View your order'])
@endsection

@section('footer_note')
    Questions about this refund? Reply to this email quoting order #{{ $order->id }}.
@endsection

