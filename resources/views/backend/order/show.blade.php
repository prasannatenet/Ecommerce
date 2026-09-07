@extends('layouts.guest')

@section('content')
<div class="container">
    <h1>Order #{{ $order->id }}</h1>
    <p>Status: {{ $order->status ?? 'N/A' }}</p>
    <h3>Items</h3>
    @if($order->items ?? false)
        <ul>
            @foreach($order->items as $it)
                <li>{{ optional($it->product)->name }} x {{ $it->quantity }}</li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
