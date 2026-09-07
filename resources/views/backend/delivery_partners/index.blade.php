@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Delivery Partners</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Delivery Partners</span>
        </nav>
        <a href="{{ route('admin.delivery-partners.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add Partner
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
        <h5 class="df-card-title"><i class="bi bi-truck"></i> All Delivery Partners</h5>
        <span class="df-badge df-badge-muted">{{ $partners->total() }} partners</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($partners as $partner)
                        <tr>
                            <td>{{ $partner->id }}</td>
                            <td><span style="font-weight:600;">{{ $partner->name }}</span></td>
                            <td><code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $partner->code }}</code></td>
                            <td>
                                @if($partner->contact_email || $partner->contact_phone)
                                    <div style="font-size:0.85rem;">{{ $partner->contact_email ?? '' }}</div>
                                    <div style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $partner->contact_phone ?? '' }}</div>
                                @else
                                    <span style="color:var(--df-text-secondary);">—</span>
                                @endif
                            </td>
                            <td>
                                @if($partner->is_active)
                                    <span class="df-badge df-badge-success">Active</span>
                                @else
                                    <span class="df-badge df-badge-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="df-actions">
                                    <a href="{{ route('admin.delivery-partners.edit', $partner) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.delivery-partners.destroy', $partner) }}" method="POST"
                                          style="display:inline" onsubmit="return confirm('Delete this delivery partner?');">
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
                            <td colspan="6">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-truck"></i></div>
                                    <p>No delivery partners found. <a href="{{ route('admin.delivery-partners.create') }}">Add your first partner</a></p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($partners->hasPages())
    <div class="mt-3">{{ $partners->links() }}</div>
@endif

@endsection
