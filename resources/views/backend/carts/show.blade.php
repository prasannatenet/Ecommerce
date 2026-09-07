@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Cart #{{ $cart->id }}</h1>
    <p>User: {{ optional($cart->user)->email }}</p>
    <p>Product: {{ optional($cart->variation->product)->name }}</p>
    <p>Quantity: {{ $cart->quantity }}</p>
    <p>Price: {{ $cart->price }}</p>
    <p>Subtotal: {{ $cart->subtotal }}</p>
</div>
@endsection
