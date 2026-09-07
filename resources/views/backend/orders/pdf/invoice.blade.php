<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->id }}</title>
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
        <div class="title">Invoice</div>
        <div class="muted">Invoice No: INV-{{ $order->id }}</div>
        <div class="muted">Date: {{ $order->created_at->format('d M Y, h:i A') }}</div>
    </div>

    <div>
        <strong>Customer</strong><br>
        {{ optional($order->user)->name ?? 'Guest' }}<br>
        {{ optional($order->user)->email ?? '-' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th>Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->sku ?? '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td class="right">Rs {{ number_format($item->unit_price, 2) }}</td>
                    <td class="right">Rs {{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <tr>
            <td><strong>Payment Method</strong></td>
            <td class="right">{{ strtoupper($order->payment_method ?? 'N/A') }}</td>
        </tr>
        <tr>
            <td><strong>Payment Status</strong></td>
            <td class="right">{{ strtoupper($order->payment_status ?? 'PENDING') }}</td>
        </tr>
        <tr>
            <td><strong>Total</strong></td>
            <td class="right"><strong>Rs {{ number_format($order->total, 2) }}</strong></td>
        </tr>
    </table>
</body>
</html>
