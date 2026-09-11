


<!-- Wishlist Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="wishlistSidebar" aria-labelledby="wishlistSidebarLabel">
    <div class="offcanvas-header border-bottom py-3">
        <h5 class="offcanvas-title fw-bold" id="wishlistSidebarLabel" style="font-size: 1.25rem;">
            <i class="bi bi-heart-fill text-danger me-2"></i> &nbsp; My Wishlist
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    
    <div class="offcanvas-body p-4 d-flex flex-column">
        @php
            $wishlistItems = $items ?? [];
            if(!isset($items) && Auth::check()) {
                $wishlistItems = App\Models\Wishlist::where('user_id', Auth::id())
                    ->with(['product.images', 'variation.product.images'])
                    ->get();
            } elseif(!isset($items) && !Auth::check()) {
                $guestIds = (array) (session('guest_wishlist', []) ?? []);
                $wishlistItems = [];
                if (! empty($guestIds)) {
                    $guestProducts = App\Models\Product::where('is_active', true)
                        ->whereIn('id', $guestIds)
                        ->with('images')
                        ->get();
                    $wishlistItems = $guestProducts->map(fn ($p) => (object) [
                        'product' => $p,
                        'variation' => null,
                        'product_variation_id' => null,
                    ])->values();
                }
            }
        @endphp

        @if(session('success'))
            <div class="alert alert-success py-2 mb-4" style="font-size:0.875rem; border-radius:8px;">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            </div>
        @endif

        @if(count($wishlistItems) == 0)
            <div class="text-center py-5 mt-auto mb-auto">
                <i class="bi bi-heart text-muted" style="font-size: 3rem;"></i>
                <p class="mt-3 text-muted">Your wishlist is currently empty.</p>
                <a href="{{ route('products.index') }}" class="btn-gehna btn-primary-gehna mt-2">Explore Products</a>
            </div>
        @else
            <div class="wishlist-items-list flex-grow-1 overflow-auto pe-2">
                @foreach($wishlistItems as $item)
                    @if($item->product)
                        <div class="d-flex flex-column align-items-center justify-content-between mb-3 border-bottom pb-3">
                            <!-- Left: Image + Info -->
                            <div class="d-flex align-items-center">
                                <div class="form-check me-2 mb-0">
                                    <input class="form-check-input wishlist-item-checkbox" type="checkbox" value="{{ $item->product->id }}" id="wishlist-check-{{ $item->product->id }}" style="cursor: pointer;">
                                </div>
                                <img src="{{ $item->product->images->isNotEmpty() ? asset('storage/' . $item->product->images->first()->path) : asset('frontend/images/no-image.png') }}" 
                                    alt="{{ $item->product->name }}" 
                                    style="width: 70px; height: 70px; object-fit: contain; background: #FFF; border-radius: 8px; margin-right: 15px; border: 1px solid #eee;">
                                
                                <div>
                                    <h6 class="mb-1" style="font-size: 0.9rem;">
                                        <a href="{{ route('product.show', $item->product->slug) }}" class="text-decoration-none text-dark">
                                            {{ $item->product->name }}
                                        </a>
                                    </h6>
                                    @if($item->variation)
                                        @php
                                            $attrs = collect($item->variation->attributes ?? [])->map(fn($v,$k) => ucfirst($k).': '.$v)->implode(' | ');
                                        @endphp
                                        <div style="font-size: 14px; color:#6C757D; margin-bottom:2px;">{{ $attrs }}</div>
                                    @endif
                                    <div class="fw-bold" style="color: #017075;">
                                        ₹{{ number_format($item->product->sale_price ?? $item->product->base_price, 2) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Buttons -->
                            <div class="w-100 d-flex gap-2 ms-2 mt-3">
                                <form action="{{ route('cart.add') }}" method="POST" class="m-0 flex-grow-1">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $item->product->id }}">
                                    @if($item->variation)
                                        <input type="hidden" name="product_variation_id" value="{{ $item->variation->id }}">
                                    @endif
                                    <input type="hidden" name="quantity" value="1">
                                    <button class="btn btn-sm btn-primary-gehna w-100 py-2 text-dark" type="submit" style="font-size: 16px; border: none; background-color: #e0f1ee; white-space: nowrap;">
                                        <i class="bi bi-cart-plus"></i> Add
                                    </button>
                                </form>

                                <form action="{{ route('wishlist.toggle') }}" method="POST" class="m-0">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $item->product->id }}">
                                    @if($item->variation)
                                        <input type="hidden" name="product_variation_id" value="{{ $item->variation->id }}">
                                    @endif
                                    <button class="btn btn-sm btn-outline-danger w-100 py-2" type="submit" style="font-size: 16px;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="wishlist-action-buttons mt-3 pt-3 border-top">
                <button type="button" class="btn btn-primary-gehna w-100 mb-2 text-white border-0 py-3" onclick="moveCheckedToCart()">
                    <i class="bi bi-cart-check me-2"></i>Move Selected to Cart
                </button>
                <button type="button" class="btn btn-outline-danger w-100" onclick="clearWishlistItems()">
                    <i class="bi bi-trash me-2"></i>Clear Wishlist
                </button>
            </div>
      @endif
    </div>
</div>
