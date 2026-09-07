<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Credit Note #{{ $refund->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 12px; }
        .header { margin-bottom: 20px; }
        .title { font-size: 22px; font-weight: bold; margin-bottom: 6px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Credit Note</div>
        <div class="muted">Credit Note No: CN-{{ $order->id }}-{{ $refund->id }}</div>
        <div class="muted">Date: {{ ($refund->processed_at ?? $refund->created_at)->format('d M Y, h:i A') }}</div>
    </div>

    <div>
        <strong>Reference Order</strong>: #{{ $order->id }}<br>
        <strong>Customer</strong>: {{ optional($order->user)->name ?? 'Guest' }}<br>
        <strong>Reason</strong>: {{ $refund->reason ?? 'N/A' }}
    </div>

    <table>
        <tr>
            <th>Refund Status</th>
            <th>Gateway Refund ID</th>
            <th class="right">Amount</th>
        </tr>
        <tr>
            <td>{{ strtoupper($refund->status) }}</td>
            <td>{{ $refund->gateway_refund_id ?? '-' }}</td>
            <td class="right">Rs {{ number_format($refund->amount, 2) }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <td><strong>Order Total</strong></td>
            <td class="right">Rs {{ number_format($order->total, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Total Refunded Till Date</strong></td>
            <td class="right">Rs {{ number_format($order->refunded_total ?? 0, 2) }}</td>
        </tr>
    </table>
</body>
</html>
