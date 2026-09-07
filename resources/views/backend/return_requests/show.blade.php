@extends('layouts.app')
@section('content')
<div class="df-page-header">
    <h1 class="df-page-title">Return Request #{{ $returnRequest->id }}</h1>
    <a href="{{ route('admin.return-requests.index') }}" class="df-btn df-btn-light"><i class="bi bi-arrow-left"></i> Back</a>
</div>
@if(session('success'))<div class="df-alert df-alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="df-alert df-alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="df-alert df-alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-4">
    <div class="col-lg-7">
        <div class="df-card"><div class="df-card-header"><h5 class="df-card-title">Requested Products</h5><span class="df-badge df-badge-muted">{{ strtoupper($returnRequest->status) }}</span></div>
            <div class="df-card-body">
                @foreach($returnRequest->items as $item)
                    <div class="d-flex justify-content-between border-bottom py-2"><span>{{ $item->orderItem->product_name }} x {{ $item->quantity }}</span><strong>₹{{ number_format($item->amount, 2) }}</strong></div>
                @endforeach
                <div class="d-flex justify-content-between pt-3"><strong>Refund value</strong><strong>₹{{ number_format($returnRequest->amount, 2) }}</strong></div>
                <p class="mt-3 mb-0"><strong>Reason:</strong> {{ $returnRequest->reason }}</p>
                @if($returnRequest->payout_method)
                    @php($payout = $returnRequest->payout_details ?? [])
                    <div class="mt-3 p-3" style="background:var(--df-bg-secondary);">
                        <strong>Refund destination: {{ strtoupper($returnRequest->payout_method) }}</strong>
                        @if($returnRequest->payout_method === 'bank')
                            <div>Account holder: {{ $payout['account_holder'] ?? '—' }}</div>
                            <div>Bank: {{ $payout['bank_name'] ?? '—' }}</div>
                            <div>Account: {{ $payout['account_number'] ?? '—' }}</div>
                            <div>IFSC: {{ $payout['ifsc'] ?? '—' }}</div>
                        @else
                            <div>UPI ID: {{ $payout['upi_id'] ?? '—' }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="df-card"><div class="df-card-header"><h5 class="df-card-title">Decision</h5></div><div class="df-card-body">
            @if($returnRequest->status === 'requested')
                <form method="POST" action="{{ route('admin.return-requests.update', $returnRequest) }}">
                    @csrf @method('PUT')
                    <label class="df-form-label">Refund method</label>
                    <select name="refund_method" class="df-form-select mb-3"><option value="gehna_coins">Gehna Coins (available immediately)</option><option value="bank_transfer">Manual bank/UPI transfer</option><option value="money">Money to original Razorpay payment</option></select>
                    <label class="df-form-label">Transfer reference <span class="text-muted">(required for bank/UPI)</span></label>
                    <input name="payout_reference" class="df-form-control mb-3" placeholder="Bank UTR or UPI reference number">
                    <label class="df-form-label">Admin note</label>
                    <textarea name="admin_note" class="df-form-control mb-3" rows="3"></textarea>
                    <div class="d-flex gap-2"><button name="decision" value="approve" class="df-btn df-btn-primary flex-fill" onclick="return confirm('Approve and issue this refund?')">Approve & Refund</button><button name="decision" value="reject" class="df-btn df-btn-danger" onclick="return confirm('Reject this request?')">Reject</button></div>
                </form>
            @else
                <p class="mb-1">Processed: {{ optional($returnRequest->processed_at)->format('d M Y, h:i A') }}</p>
                <p class="mb-0">Method: {{ ucfirst(str_replace('_', ' ', $returnRequest->refund_method ?? 'N/A')) }}</p>
                <p class="mt-2 mb-0">{{ $returnRequest->admin_note }}</p>
            @endif
        </div></div>
    </div>
</div>
@endsection
