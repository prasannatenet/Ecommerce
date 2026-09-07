@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">Newsletter Leads</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">Newsletter Leads</span>
        </nav>
    </div>
</div>

@if(session('success'))
    <div class="df-alert df-alert-success">
        <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
@endif

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-envelope-paper"></i> Subscribers</h5>
        <span class="df-badge df-badge-muted">{{ $subscribers->total() }} emails</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th style="width:90px;">#</th>
                        <th>Email</th>
                        <th>Subscribed At</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscribers as $subscriber)
                        <tr>
                            <td>{{ $subscriber->id }}</td>
                            <td style="font-weight:600;">{{ $subscriber->email }}</td>
                            <td style="color:var(--df-text-secondary); font-size:0.85rem;">
                                {{ optional($subscriber->subscribed_at ?: $subscriber->created_at)->format('M d, Y h:i A') }}
                            </td>
                            <td>
                                <form action="{{ route('admin.newsletter-subscribers.destroy', $subscriber) }}" method="POST" onsubmit="return confirm('Remove this subscriber?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="df-action-btn danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="df-empty-state">
                                    <div class="empty-icon"><i class="bi bi-envelope-paper"></i></div>
                                    <p>No newsletter subscribers yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($subscribers->hasPages())
    <div class="mt-3">{{ $subscribers->links() }}</div>
@endif

@endsection
