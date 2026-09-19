<p>Hello {{ optional($user)->name ?? 'Customer' }},</p>

<p>We've received your return request <strong>#{{ $returnRequest->id }}</strong> for order <strong>#{{ $order->id }}</strong>.</p>

<p>Return Details:</p>
<ul>
    <li>Reason: {{ $returnRequest->reason ?? 'N/A' }}</li>
    <li>Payout Method: {{ strtoupper($returnRequest->payout_method ?? 'N/A') }}
        @if($returnRequest->payout_method === 'bank')
            ({{ $returnRequest->payout_details['bank_name'] ?? '' }})
        @elseif($returnRequest->payout_method === 'upi')
            ({{ $returnRequest->payout_details['upi_id'] ?? '' }})
        @endif
    </li>
    <li>Refund Amount: Rs {{ number_format((float) $returnRequest->amount, 2) }}</li>
    <li>Status: {{ strtoupper($returnRequest->status ?? 'REQUESTED') }}</li>
</ul>

<p>Our team will review your request and process the refund to your preferred payout method. You'll receive another email once the return is approved and the refund is initiated.</p>

<p>If you have any questions, reply to this email with your return request number (#{{ $returnRequest->id }}).</p>

<p>Thank you for shopping with us.</p>
