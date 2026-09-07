<div class="pm-product-card">

    {{-- Image --}}
    <div class="pm-product-img-wrap">
        <a href="{{ route('product.show', $product->slug) }}">
            @if($product->images->isNotEmpty())
                <img src="{{ asset('storage/' . $product->images->first()->path) }}"
                     alt="{{ $product->name }}" loading="lazy">
            @else
                <div style="width:100%; aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#ccc; background:#f9f9f9;">
                    <i class="bi bi-image" style="font-size:2rem; margin-bottom:6px;"></i>
                    <span style="font-size:0.78rem;">No Image</span>
                </div>
            @endif
        </a>

        {{-- NEW Badge --}}
        @if($product->created_at->diffInDays(now()) <= 30)
            <span class="pm-product-badge-new">NEW</span>
        @endif

        {{-- Sale Badge --}}
        @if($product->sale_price)
            @php $discount = round((($product->base_price - $product->sale_price) / $product->base_price) * 100); @endphp
            <span style="position:absolute; top:10px; right:10px; background:#dc3545; color:#fff; font-size:0.7rem; font-weight:700; padding:4px 8px; border-radius:4px; z-index:2;">
                -{{ $discount }}%
            </span>
        @endif
    </div>

    {{-- Info --}}
    <div class="pm-product-info">
        @if(optional($product->brand)->name || optional($product->category)->name)
            <p style="color:#017075; font-size:0.72rem; text-transform:uppercase; letter-spacing:1.5px; font-weight:700; margin:0 0 4px;">
                {{ optional($product->brand)->name ?? optional($product->category)->name }}
            </p>
        @endif

        <a href="{{ route('product.show', $product->slug) }}"
           style="font-size:0.92rem; font-weight:700; color:#0D0D0D; text-decoration:none; display:block; margin-bottom:8px; line-height:1.3; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
            {{ $product->name }}
        </a>

        <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
            @if($product->sale_price)
                <span class="pm-product-price" style="color:#017075;">Rs {{ number_format($product->sale_price, 2) }}</span>
                <span class="pm-product-price-old">Rs {{ number_format($product->base_price, 2) }}</span>
            @else
                <span class="pm-product-price" style="color:#017075;">Rs {{ number_format($product->base_price, 2) }}</span>
            @endif
        </div>

        {{-- Actions --}}
        <div class="pm-product-actions">
            {{-- Add to Cart --}}
            @if($product->variations->isNotEmpty())
                <a href="{{ route('product.show', $product->slug) }}" class="pm-action-btn pm-btn-cart w-100 text-center text-decoration-none">
                    <i class="bi bi-sliders"></i> Select Options
                </a>
            @else
                <form action="{{ route('cart.add') }}" method="POST" style="flex:1;">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="pm-action-btn pm-btn-cart w-100">
                        <i class="bi bi-cart-plus"></i> Add to Cart
                    </button>
                </form>
            @endif

            {{-- Wishlist --}}
            @php
                $inWishlist = false;
                if (Auth::check()) {
                    $inWishlist = \App\Models\Wishlist::where('user_id', Auth::id())->where('product_id', $product->id)->exists();
                } else {
                    foreach ((array) session('guest_wishlist', []) as $gid) {
                        if ((int) $gid === (int) $product->id) {
                            $inWishlist = true;
                            break;
                        }
                    }
                }
            @endphp
            <button type="button" class="pm-action-btn wishlist-btn-ajax"
                    title="Wishlist"
                    data-product-id="{{ $product->id }}"
                    style="background:#fff; border:1.5px solid #DEE2E6; color:#6C757D; width:42px; flex-shrink:0;"
                    onclick="toggleAjaxWishlist({{ $product->id }}, this)">
                @if($inWishlist)
                    <i class="bi bi-heart-fill" style="color: red;"></i>
                @else
                    <i class="bi bi-heart"></i>
                @endif
            </button>

            {{-- Quick View --}}
            <button type="button" class="pm-action-btn"
                    title="Quick View"
                    style="background:#fff; border:1.5px solid #DEE2E6; color:#6C757D; width:42px; flex-shrink:0;"
                    onmouseover="this.style.borderColor='#017075'; this.style.color='#017075';"
                    onmouseout="this.style.borderColor='#DEE2E6'; this.style.color='#6C757D';"
                    onclick="openQuickView({{ $product->id }}, '{{ addslashes($product->name) }}')">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

</div>
