@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Cart Items</h1>
    <table class="table">
        <thead>
            <tr><th>ID</th><th>User</th><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @foreach($carts as $c)
            <tr>
                <td>{{ $c->id }}</td>
                <td>{{ optional($c->user)->email }}</td>
                <td>{{ optional($c->variation->product)->name }}</td>
                <td>{{ $c->quantity }}</td>
                <td>{{ $c->price }}</td>
                <td>{{ $c->subtotal }}</td>
                <td>
                    <a href="{{ route('admin.carts.show', $c) }}" class="btn btn-sm btn-primary">View</a>
                    <form method="POST" action="{{ route('admin.carts.destroy', $c) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $carts->links() }}
</div>
@endsection
