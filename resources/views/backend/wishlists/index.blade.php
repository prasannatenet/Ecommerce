@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Wishlists</h1>
    <table class="table">
        <thead><tr><th>ID</th><th>User</th><th>Product</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($items as $it)
            <tr>
                <td>{{ $it->id }}</td>
                <td>{{ optional($it->user)->email }}</td>
                <td>{{ optional($it->product)->name }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.wishlists.destroy', $it) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $items->links() }}
</div>
@endsection
