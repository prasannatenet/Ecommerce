@extends('layouts.app')

@section('content')
<div class="df-page-header">
    <h1 class="df-page-title">Product Reviews</h1>
    <nav class="df-breadcrumb d-none d-md-flex">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="separator"><i class="bi bi-chevron-right"></i></span>
        <span class="current">Product Reviews</span>
    </nav>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="df-stat-card">
            <div class="df-stat-icon blue"><i class="bi bi-chat-square-text"></i></div>
            <div><div class="df-stat-value">{{ $totalReviews }}</div><div class="df-stat-title">Matching Reviews</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-stat-card">
            <div class="df-stat-icon orange"><i class="bi bi-star-fill"></i></div>
            <div><div class="df-stat-value">{{ number_format($averageRating, 1) }}</div><div class="df-stat-title">Average Rating</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="df-stat-card">
            <div class="df-stat-icon green"><i class="bi bi-images"></i></div>
            <div><div class="df-stat-value">{{ $reviewsWithImages }}</div><div class="df-stat-title">With Photos</div></div>
        </div>
    </div>
</div>

<div class="df-card mb-4">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-funnel"></i> Filters</h5>
    </div>
    <div class="df-card-body">
        <form method="GET" action="{{ route('admin.reviews.index') }}" class="row g-3">
            <div class="col-md-4">
                <label class="df-form-label">Search</label>
                <input type="search" name="search" class="df-form-control" value="{{ request('search') }}" placeholder="Review, customer, email, or product">
            </div>
            <div class="col-md-2">
                <label class="df-form-label">Rating</label>
                <select name="rating" class="df-form-select">
                    <option value="">All ratings</option>
                    @for ($rating = 5; $rating >= 1; $rating--)
                        <option value="{{ $rating }}" @selected((string) request('rating') === (string) $rating)>{{ $rating }} star{{ $rating === 1 ? '' : 's' }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3">
                <label class="df-form-label">Product</label>
                <select name="product_id" class="df-form-select">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="df-form-label">Date from</label>
                <input type="date" name="date_from" class="df-form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="df-form-label">Date to</label>
                <input type="date" name="date_to" class="df-form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="df-btn df-btn-primary" type="submit"><i class="bi bi-search"></i> Apply Filters</button>
                <a class="df-btn df-btn-light" href="{{ route('admin.reviews.index') }}">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="df-card">
    <div class="df-card-header">
        <h5 class="df-card-title"><i class="bi bi-chat-square-text"></i> All Reviews</h5>
        <span class="df-badge df-badge-muted">{{ $totalReviews }} found</span>
    </div>
    <div class="df-card-body-flush">
        <div class="table-responsive">
            <table class="df-table">
                <thead><tr><th>Product &amp; Customer</th><th>Rating</th><th>Review</th><th>Photos</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse ($reviews as $review)
                        <tr>
                            <td style="min-width:220px">
                                <a href="{{ route('product.show', $review->product->slug) }}" target="_blank" rel="noopener" style="font-weight:700;color:var(--df-primary)">{{ $review->product->name }}</a>
                                <div style="font-size:.82rem;color:var(--df-text-secondary)">{{ $review->user->name ?? 'Deleted user' }}</div>
                                <div style="font-size:.78rem;color:var(--df-text-muted)">{{ $review->user->email ?? '—' }}</div>
                            </td>
                            <td>
                                <span class="df-badge df-badge-warning">{{ $review->rating }} / 5</span>
                                <div class="text-warning" aria-label="{{ $review->rating }} out of 5 stars">
                                    @for ($star = 1; $star <= 5; $star++)<i class="bi bi-star{{ $review->rating >= $star ? '-fill' : '' }}"></i>@endfor
                                </div>
                            </td>
                            <td style="min-width:280px;max-width:520px">{{ $review->comment }}</td>
                            <td>
                                @if ($review->images->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-2" style="max-width:210px">
                                        @foreach ($review->images as $image)
                                            <a href="{{ $image->url }}" target="_blank" rel="noopener" title="Open review photo">
                                                <img src="{{ $image->url }}" alt="Review photo for {{ $review->product->name }}" style="width:54px;height:54px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb">
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap">{{ $review->created_at->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="df-empty-state"><div class="empty-icon"><i class="bi bi-chat-square-text"></i></div><p>No reviews match these filters.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">{{ $reviews->links() }}</div>
@endsection
