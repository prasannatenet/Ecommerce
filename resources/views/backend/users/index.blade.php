@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Users</h1>
    <table class="table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($users as $u)
            <tr>
                <td>{{ $u->id }}</td>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td>
                    <a class="btn btn-sm btn-secondary" href="{{ route('admin.users.edit', $u) }}">Edit</a>
                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $users->links() }}
</div>
@endsection
