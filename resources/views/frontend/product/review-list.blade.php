{{-- Product review cards — used on the product detail page and re-rendered by AJAX after a new review is posted/edited/deleted. --}}
@if ($reviews->isEmpty())
    <div class="review-empty text-muted py-3">No reviews yet. Be the first to review this product.</div>
@else
    @foreach ($reviews as $review)
        <div class="review-card" data-review-id="{{ $review->id }}">
            <div class="review-header">
                <span class="review-author">{{ $review->user->name ?? 'GEHNA Customer' }}</span>
                <span class="review-date">{{ $review->created_at->format('F j, Y') }}</span>
            </div>
            <div class="review-stars">
                @for ($i = 1; $i <= 5; $i++)
                    <i class="bi {{ $review->rating >= $i ? 'bi-star-fill' : 'bi-star' }}"></i>
                @endfor
            </div>
            <p class="review-text">{{ $review->comment }}</p>
            @if (Auth::check() && (int) $review->user_id === (int) Auth::id())
                <div class="review-actions mt-2 d-flex gap-2">
                    <button type="button" class="btn btn-sm review-edit-btn"
                        data-review-id="{{ $review->id }}"
                        data-rating="{{ $review->rating }}"
                        data-comment="{{ $review->comment }}">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>
                    <form method="POST"
                        action="{{ route('products.reviews.destroy', [$product, $review]) }}"
                        class="review-delete-form" data-review-id="{{ $review->id }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm review-delete-btn">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </form>
                </div>
            @endif
        </div>
    @endforeach
@endif
