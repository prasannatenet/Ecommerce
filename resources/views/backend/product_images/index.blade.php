@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Product Images</h1>
    <table class="table">
        <thead><tr><th>ID</th><th>Product</th><th>Path</th><th>Primary</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($items as $it)
            <tr>
                <td>{{ $it->id }}</td>
                <td>{{ optional($it->product)->name }}</td>
                <td>{{ $it->path }}</td>
                <td>{{ $it->is_primary }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.product-images.destroy', $it) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $items->links() }}
</div>
@endsection
