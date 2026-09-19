@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Delivery Partner Settings</h1>
    <nav class="df-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <a href="{{ route('admin.delivery-partners.index') }}">Delivery Partners</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">{{ $partner->name }}</span>
    </nav>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <div style="white-space:pre-line;">{{ session('success') }}</div>
    </div>
@endif

@if(session('delivery_error'))
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div style="white-space:pre-line;">{{ session('delivery_error') }}</div>
    </div>
@endif

@if($errors->any())
    <div class="df-alert df-alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <form action="{{ route('admin.delivery-partners.update', $partner) }}" method="POST">
            @csrf
            @method('PUT')
            @include('backend.delivery_partners.partials.form', ['partner' => $partner])
        </form>
    </div>
    <div class="col-lg-4">
        <div class="df-card">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-info-circle"></i> Partner Info</h5>
            </div>
            <div class="df-card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Status</span>
                    @if($partner->is_active)
                        <span class="df-badge df-badge-success">Active</span>
                    @else
                        <span class="df-badge df-badge-danger">Inactive</span>
                    @endif
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Code</span>
                    <code style="font-size:0.82rem; color:var(--df-text-secondary);">{{ $partner->code }}</code>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Default partner</span>
                    <span class="df-badge {{ $partner->is_default ? 'df-badge-info' : 'df-badge-muted' }}">{{ $partner->is_default ? 'Yes' : 'No' }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Integration</span>
                    <span class="df-badge {{ $partner->isIntegrated() ? 'df-badge-purple' : 'df-badge-muted' }}">
                        {{ $partner->isIntegrated() ? $partner->driver : 'Manual' }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">API token</span>
                    <span class="df-badge {{ $partner->hasStoredApiKey() ? 'df-badge-success' : 'df-badge-warning' }}">
                        {{ $partner->hasStoredApiKey() ? $partner->maskedApiKey() : 'Not set' }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Auto-book</span>
                    <span class="df-badge {{ $partner->autoBookEnabled() ? 'df-badge-success' : 'df-badge-muted' }}">
                        {{ $partner->autoBookEnabled() ? $partner->auto_book_on : 'Off' }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Tracking updates</span>
                    <span class="df-badge {{ $partner->auto_sync_tracking ? 'df-badge-success' : 'df-badge-muted' }}">
                        {{ $partner->auto_sync_tracking ? 'Automatic' : 'Off' }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Customer emails</span>
                    <span class="df-badge {{ $partner->auto_notify_customer ? 'df-badge-success' : 'df-badge-muted' }}">
                        {{ $partner->auto_notify_customer ? 'Automatic' : 'Off' }}
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="df-form-label mb-0">Shipments</span>
                    <a href="{{ route('admin.shipments.index', ['partner' => $partner->id]) }}" class="df-badge df-badge-primary">
                        {{ $partner->shipments()->count() }} total
                    </a>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="df-form-label mb-0">Updated</span>
                    <span style="font-size:0.85rem; color:var(--df-text-secondary);">{{ $partner->updated_at->format('M d, Y H:i') }}</span>
                </div>
            </div>
        </div>

        <div class="df-card mt-4">
            <div class="df-card-header">
                <h5 class="df-card-title"><i class="bi bi-question-circle"></i> How it works</h5>
            </div>
            <div class="df-card-body">
                <ol style="padding-left:18px; margin:0; font-size:0.88rem; color:var(--df-text-secondary); line-height:1.9;">
                    <li>Admin configures this partner once.</li>
                    <li>Customer places an order.</li>
                    <li>Laravel checks serviceability and books the shipment.</li>
                    <li>Delhivery returns the AWB and handles delivery.</li>
                    <li>Status updates flow back into the admin and customer views.</li>
                </ol>
                <p class="df-form-hint mb-0 mt-3">
                    <i class="bi bi-info-circle"></i>
                    Webhook callbacks are instant; the scheduled tracking job reconciles anything missed.
                </p>
            </div>
        </div>
    </div>
</div>

@endsection
