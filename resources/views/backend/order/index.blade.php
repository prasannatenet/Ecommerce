@extends('layouts.guest')

@section('content')
<div class="container">
    <h1>Your Orders</h1>
    @if(empty($orders) || count($orders) === 0)
        <p>No orders yet.</p>
    @else
        <ul>
            @foreach($orders as $o)
                <li>Order #{{ $o->id }}</li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
