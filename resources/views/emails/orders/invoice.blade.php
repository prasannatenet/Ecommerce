<p>Hello {{ optional($order->user)->name ?? 'Customer' }},</p>

<p>Your invoice for order <strong>#{{ $order->id }}</strong> is attached with this email.</p>

<p>Payment Method: {{ strtoupper($order->payment_method ?? 'N/A') }}<br>
Payment Status: {{ strtoupper($order->payment_status ?? 'PENDING') }}<br>
Order Total: Rs {{ number_format($order->total, 2) }}</p>

<p>Thank you for shopping with us.</p>
