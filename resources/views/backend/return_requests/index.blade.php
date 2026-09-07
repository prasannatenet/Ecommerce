@extends('layouts.app')
@section('content')
<div class="df-page-header">
    <h1 class="df-page-title">Return & Refund Requests</h1>
</div>
@if(session('success'))<div class="df-alert df-alert-success">{{ session('success') }}</div>@endif
<div class="df-card">
    <div class="df-card-body-flush table-responsive">
        <table class="df-table">
            <thead><tr><th>Request</th><th>Customer</th><th>Order</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($requests as $request)
                <tr>
                    <td>#{{ $request->id }}<br><small>{{ $request->created_at->format('d M Y, h:i A') }}</small></td>
                    <td>{{ $request->user->name ?? 'Guest' }}<br><small>{{ $request->user->email ?? '' }}</small></td>
                    <td><a href="{{ route('admin.orders.show', $request->order) }}">#{{ $request->order_id }}</a></td>
                    <td>₹{{ number_format($request->amount, 2) }}</td>
                    <td><span class="df-badge df-badge-muted">{{ strtoupper($request->status) }}</span></td>
                    <td><a class="df-btn df-btn-light df-btn-sm" href="{{ route('admin.return-requests.show', $request) }}">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="df-empty-state"><p>No return requests found.</p></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $requests->links() }}</div>
</div>
@endsection
