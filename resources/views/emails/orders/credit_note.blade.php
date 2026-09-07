<p>Hello {{ optional($order->user)->name ?? 'Customer' }},</p>

<p>A credit note has been generated for your order <strong>#{{ $order->id }}</strong>.</p>

<p>Refund Amount: Rs {{ number_format($refund->amount, 2) }}<br>
Refund Status: {{ strtoupper($refund->status) }}<br>
Reason: {{ $refund->reason ?? 'N/A' }}</p>

<p>Your credit note PDF is attached to this email.</p>
