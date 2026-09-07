@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Payment Providers</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Payment Providers</span>
        </nav>
        <a href="{{ route('admin.payment-providers.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Provider
        </a>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-credit-card-2-front"></i> All Payment Providers</h5>
        <span class="df-badge df-badge-muted">{{ $providers->total() }} providers</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Public Key</th>
                        <th>Secret Key</th>
                        <th>Status</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($providers as $provider)
                        <tr>
                            <td>{{ $provider->id }}</td>
                            <td><span style="font-weight:600;">{{ $provider->name }}</span></td>
                            <td><code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $provider->slug }}</code></td>
                            <td style="max-width:160px;">
                                @if($provider->public_key)
                                    <code style="font-size:0.78rem; color:var(--df-text-secondary);" title="{{ $provider->public_key }}">{{ Str::limit($provider->public_key, 20) }}</code>
                                @else
                                    <span style="color:var(--df-text-secondary);">—</span>
                                @endif
                            </td>
                            <td>
                                @if($provider->secret_key)
                                    <span style="font-size:0.82rem; color:var(--df-text-secondary); letter-spacing:2px;">••••••••</span>
                                @else
                                    <span style="color:var(--df-text-secondary);">—</span>
                                @endif
                            </td>
                            <td>
                                @if($provider->is_active)
                                    <span class="df-badge df-badge-success">Active</span>
                                @else
                                    <span class="df-badge df-badge-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="df-actions">
                                    <form action="{{ route('admin.payment-providers.toggle', $provider) }}" method="POST" style="display:inline">
                                        @csrf
                                        <button class="df-action-btn {{ $provider->is_active ? 'danger' : 'success' }}" title="{{ $provider->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="bi {{ $provider->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.payment-providers.edit', $provider) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.payment-providers.destroy', $provider) }}" method="POST"
                                          style="display:inline" onsubmit="return confirm('Delete this provider?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="df-action-btn danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-credit-card-2-front"></i></div>
                                    <p>No payment providers configured. <a href="{{ route('admin.payment-providers.create') }}">Add your first provider</a></p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($providers->hasPages())
    <div class="mt-3">{{ $providers->links() }}</div>
@endif

@endsection
