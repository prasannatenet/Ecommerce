@extends('emails.layout')

@section('title', 'Return Request #' . $returnRequest->id)
@section('header_note', 'Return Request')
@section('preheader', 'We have received return request #' . $returnRequest->id . ' for order #' . $order->id . '.')
@section('eyebrow', 'Return #' . $returnRequest->id)
@section('heading', 'We have received your return request')

@section('content')
    @php
        $payoutDetails = (array) ($returnRequest->payout_details ?? []);
        $payoutReference = null;

        if ($returnRequest->payout_method === 'bank') {
            $payoutReference = $payoutDetails['bank_name'] ?? null;

            if (! $payoutReference && ! empty($payoutDetails['account_number'])) {
                $payoutReference = 'A/C ending ' . substr((string) $payoutDetails['account_number'], -4);
            }
        } elseif ($returnRequest->payout_method === 'upi') {
            $payoutReference = $payoutDetails['upi_id'] ?? null;
        }
    @endphp

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#EFF5F1;border:1px solid #DCE7E1;border-radius:14px;margin:0 0 20px;">
        <tr>
            <td style="padding:16px 20px;font-size:15px;line-height:23px;color:#0F3D32;">
                <div style="font-size:15px;">Hello <strong>{{ optional($user)->name ?? optional($order->user)->name ?? 'Customer' }}</strong>, &#128717;</div>
                <div style="margin-top:6px;font-size:14px;line-height:22px;color:#3E5A51;">Thank you for letting us know. Our team is reviewing your return for order <strong style="color:#0F3D32;">#{{ $order->id }}</strong>, and we will email you again as soon as it has been approved and the refund is initiated.</div>
            </td>
        </tr>
    </table>

    @include('emails.partials.detail-rows', ['title' => 'Return summary', 'rows' => array_values(array_filter([
        ['label' => 'Return Request', 'value' => '#' . $returnRequest->id],
        ['label' => 'Order No', 'value' => '#' . $order->id],
        ['label' => 'Reason', 'value' => $returnRequest->reason ?: 'N/A'],
        [
            'label' => 'Payout Method',
            'value' => strtoupper($returnRequest->payout_method ?? 'N/A')
                . ($payoutReference ? ' — ' . $payoutReference : ''),
        ],
        ['label' => 'Refund Amount', 'value' => 'Rs ' . number_format((float) $returnRequest->amount, 2), 'strong' => true],
        ['label' => 'Status', 'value' => strtoupper($returnRequest->status ?? 'REQUESTED')],
    ]))])

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;background-color:#FAF7F0;border:1px dashed #D9CFB8;border-radius:12px;margin:18px 0 0;">
        <tr>
            <td style="padding:13px 18px;font-size:13px;line-height:21px;color:#4A443C;">
                &#128666;&nbsp; The refund is credited to your chosen payout method once the returned item is received and inspected.
            </td>
        </tr>
    </table>
@endsection

@section('cta')
    @include('emails.partials.button', ['url' => route('orders.show', $order), 'label' => 'View order & return status'])
@endsection

@section('footer_note')
    Questions about this return? Reply to this email quoting return request #{{ $returnRequest->id }}.
@endsection

