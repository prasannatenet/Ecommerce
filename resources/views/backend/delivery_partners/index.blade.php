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
        <a href="{{ route('admin.shipments.index') }}" class="df-btn df-btn-light">
            <i class="bi bi-box-seam"></i> Shipments
        </a>
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
                        <th>Partner</th>
                        <th>Environment</th>
                        <th>Automatic rules</th>
                        <th>Shipments</th>
                        <th>Status</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($partners as $partner)
                        <tr>
                            <td>{{ $partner->id }}</td>
                            <td>
                                <span style="font-weight:600;">{{ $partner->name }}</span>
                                <div style="font-size:0.78rem; color:var(--df-text-secondary);">
                                    <code style="font-size:0.75rem;">{{ $partner->code }}</code>
                                    @if($partner->isIntegrated())
                                        · {{ $partner->driver }}
                                    @endif
                                    @if($partner->contact_email)
                                        · {{ $partner->contact_email }}
                                    @endif
                                    @if($partner->is_default)
                                        <span class="df-badge df-badge-info" style="font-size:0.7rem;">Default</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($partner->isIntegrated())
                                    @if($partner->is_sandbox)
                                        <span class="df-badge df-badge-warning">Sandbox</span>
                                    @else
                                        <span class="df-badge df-badge-success">Production</span>
                                    @endif
                                    @if($partner->hasStoredApiKey())
                                        <div style="font-size:0.72rem; color:var(--df-text-secondary); margin-top:4px;">
                                            <i class="bi bi-shield-lock"></i> {{ $partner->maskedApiKey() }}
                                        </div>
                                    @endif
                                @else
                                    <span class="df-badge df-badge-muted">Manual</span>
                                @endif
                            </td>
                            <td style="font-size:0.8rem;">
                                <div>
                                    Auto-book:
                                    @if($partner->autoBookEnabled())
                                        <span class="df-badge df-badge-success">On ({{ $partner->auto_book_on }})</span>
                                    @else
                                        <span class="df-badge df-badge-muted">Off</span>
                                    @endif
                                </div>
                                <div class="mt-1">
                                    Tracking:
                                    <span class="df-badge {{ $partner->auto_sync_tracking ? 'df-badge-success' : 'df-badge-muted' }}">
                                        {{ $partner->auto_sync_tracking ? 'Auto' : 'Off' }}
                                    </span>
                                </div>
                                <div class="mt-1">
                                    Emails:
                                    <span class="df-badge {{ $partner->auto_notify_customer ? 'df-badge-success' : 'df-badge-muted' }}">
                                        {{ $partner->auto_notify_customer ? 'Auto' : 'Off' }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('admin.shipments.index', ['partner' => $partner->id]) }}"
                                   class="df-badge df-badge-primary">{{ $partner->shipments()->count() }}</a>
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
                                    <a href="{{ route('admin.delivery-partners.edit', $partner) }}" class="df-action-btn info" title="Delivery settings">
                                        <i class="bi bi-gear"></i>
                                    </a>
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
                            <td colspan="7">
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
