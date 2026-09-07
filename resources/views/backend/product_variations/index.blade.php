@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Product Variations</h1>
    <a class="btn btn-primary mb-3" href="{{ route('admin.product-variations.create') }}">Create</a>
    <table class="table">
        <thead><tr><th>ID</th><th>Product</th><th>SKU</th><th>Price</th><th>Stock</th><th>Active</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($items as $it)
            <tr>
                <td>{{ $it->id }}</td>
                <td>{{ optional($it->product)->name }}</td>
                <td>{{ $it->sku }}</td>
                <td>{{ $it->price }}</td>
                <td>{{ $it->stock }}</td>
                <td>{{ $it->is_active }}</td>
                <td>
                    <a href="{{ route('admin.product-variations.edit', $it) }}" class="btn btn-sm btn-secondary">Edit</a>
                    <form method="POST" action="{{ route('admin.product-variations.destroy', $it) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $items->links() }}
</div>
@endsection
