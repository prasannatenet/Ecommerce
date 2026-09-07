@extends('layouts.app')
@section('content')

<div class="df-page-header">
    <h1 class="df-page-title">FAQs</h1>
    <div class="d-flex align-items-center gap-3">
        <nav class="df-breadcrumb d-none d-md-flex">
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <span class="separator"><i class="bi bi-chevron-right"></i></span>
            <span class="current">FAQs</span>
        </nav>
        <a href="{{ route('admin.faqs.create') }}" class="df-btn df-btn-primary">
            <i class="bi bi-plus-lg"></i> Add FAQ
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
        <h5 class="df-card-title"><i class="bi bi-question-circle"></i> FAQ Items</h5>
        <span class="df-badge df-badge-muted">{{ $faqs->total() }} items</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Question</th>
                        <th>Answer</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($faqs as $faq)
                        <tr>
                            <td>{{ $faq->id }}</td>
                            <td style="font-weight:600;">{{ $faq->question }}</td>
                            <td style="color:var(--df-text-secondary);">{{ \Illuminate\Support\Str::limit($faq->answer, 90) }}</td>
                            <td><span class="df-badge df-badge-info">{{ $faq->sort_order }}</span></td>
                            <td>
                                @if($faq->is_active)
                                    <span class="df-badge df-badge-success">Active</span>
                                @else
                                    <span class="df-badge df-badge-muted">Inactive</span>
                                @endif
                            </td>
                            <td style="color:var(--df-text-secondary); font-size:0.85rem;">{{ $faq->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="df-actions">
                                    <a href="{{ route('admin.faqs.edit', $faq) }}" class="df-action-btn warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" style="display:inline" onsubmit="return confirm('Delete this FAQ?')">
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
                                    <div class="empty-icon"><i class="bi bi-question-circle"></i></div>
                                    <p>No FAQs created yet. <a href="{{ route('admin.faqs.create') }}">Create your first FAQ</a></p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $faqs->links() }}</div>

@endsection
