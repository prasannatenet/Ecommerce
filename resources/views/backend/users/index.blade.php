@extends('layouts.app')

@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Registered Users</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Registered Users</span>
        </nav>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-circle-fill"></i> {{ session('error') }}
    </div>
@endif

{{-- Stats --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="df-card" style="padding:18px 20px;">
            <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:var(--df-text-secondary);">Total Users</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--df-text);">{{ number_format($stats['total']) }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="df-card" style="padding:18px 20px;">
            <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:var(--df-text-secondary);">Customers</div>
            <div style="font-size:1.6rem; font-weight:800; color:#017075;">{{ number_format($stats['customers']) }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="df-card" style="padding:18px 20px;">
            <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:var(--df-text-secondary);">Admins</div>
            <div style="font-size:1.6rem; font-weight:800; color:#A26D0D;">{{ number_format($stats['admins']) }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="df-card" style="padding:18px 20px;">
            <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; color:var(--df-text-secondary);">With Gehna Coins</div>
            <div style="font-size:1.6rem; font-weight:800; color:#198754;">{{ number_format($stats['with_coins']) }}</div>
        </div>
    </div>
</div>

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-people"></i> All Users</h5>
        <span class="df-badge df-badge-muted">{{ number_format($users->total()) }} users</span>
    </div>

    <div style="padding:14px 20px; border-bottom:1px solid var(--df-border);">
        <form method="GET" action="{{ route('admin.users.index') }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search by name, email or mobile..."
                   style="flex:1 1 240px; padding:9px 14px; border-radius:8px; border:1px solid var(--df-border); font-size:0.9rem;">
            <select name="role" style="padding:9px 12px; border-radius:8px; border:1px solid var(--df-border); font-size:0.9rem;">
                <option value="all" {{ $roleFilter === 'all' ? 'selected' : '' }}>All roles</option>
                <option value="user" {{ $roleFilter === 'user' ? 'selected' : '' }}>Customers</option>
                <option value="admin" {{ $roleFilter === 'admin' ? 'selected' : '' }}>Admins</option>
            </select>
            <button type="submit" class="df-btn df-btn-primary" style="padding:9px 18px;">
                <i class="bi bi-search"></i> Search
            </button>
            @if($search !== '' || $roleFilter !== 'all')
                <a href="{{ route('admin.users.index') }}" class="df-btn df-btn-secondary" style="padding:9px 14px;">Reset</a>
            @endif
        </form>
    </div>

    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>User</th>
                        <th>Mobile</th>
                        <th>Role</th>
                        <th style="width:130px;">Gehna Coins</th>
                        <th style="width:90px;">Orders</th>
                        <th style="width:150px;">Registered</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php $isAdmin = $user->hasRole('admin'); @endphp
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span style="width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg, #022C2B 0%, #017075 100%); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:0.85rem; flex-shrink:0;">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <div style="font-weight:600;">{{ $user->name }}</div>
                                        <div style="color:var(--df-text-secondary); font-size:0.82rem;">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:0.85rem;">{{ $user->phone ?: '—' }}</td>
                            <td>
                                @if($isAdmin)
                                    <span class="df-badge df-badge-warning"><i class="bi bi-shield-lock"></i> Admin</span>
                                @else
                                    <span class="df-badge df-badge-success">Customer</span>
                                @endif
                            </td>
                            <td>
                                <span class="df-badge {{ $user->gehna_coins > 0 ? 'df-badge-primary' : 'df-badge-muted' }}">
                                    <i class="bi bi-coin"></i> {{ number_format((int) $user->gehna_coins) }}
                                </span>
                            </td>
                            <td>{{ number_format((int) $user->orders_count) }}</td>
                            <td style="color:var(--df-text-secondary); font-size:0.85rem;">
                                {{ $user->created_at->format('M d, Y') }}
                                <div style="font-size:0.75rem;">{{ $user->created_at->format('h:i A') }}</div>
                            </td>
                            <td>
                                @if(!$isAdmin && $user->id !== auth()->id())
                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                          onsubmit="return confirm('Remove {{ $user->name }} permanently?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="df-action-btn danger" title="Remove user">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @else
                                    <span style="color:var(--df-text-secondary);" title="Protected account"><i class="bi bi-lock"></i></span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-people"></i></div>
                                    <p>No users found{{ $search !== '' ? ' for "' . $search . '"' : '' }}.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($users->hasPages())
    <div class="mt-3">{{ $users->links() }}</div>
@endif

@endsection

