@foreach($products as $product)
    @php
        $effPrice = $product->sale_price ?? $product->base_price;
        $hasImages = $product->images->isNotEmpty();
        $hasVars = $product->variations->isNotEmpty();
        $isWishlisted = in_array((int) $product->id, $wishlistProductIds ?? [], true);
        $discount = $product->sale_price
            ? round((($product->base_price - $product->sale_price) / $product->base_price) * 100)
            : 0;
    @endphp
    <div class="col-6 col-md-3">
        <div class="shop-card">

            {{-- Image --}}
            <a href="{{ route('product.show', $product->slug) }}" class="shop-card-img-wrap">
                @if($hasImages)
                    @php
                        $firstImage = $product->images->first();
                        $imagePath = $firstImage->path ?? $firstImage->image_path ?? null;
                    @endphp
                    <img src="{{ $imagePath ? asset('storage/' . ltrim($imagePath, '/')) : asset('frontend/images/dumbbell.png') }}"
                        alt="{{ $product->name }}" loading="lazy">
                @else
                    <div class="shop-card-no-img">
                        <i class="bi bi-image"></i>
                        <span>No Image</span>
                    </div>
                @endif

                @if($product->created_at->diffInDays(now()) <= 30)
                    <span class="shop-badge shop-badge-new">NEW</span>
                @endif
                @if($discount > 0)
                    <span class="shop-badge shop-badge-sale">-{{ $discount }}%</span>
                @endif
            </a>

            {{-- Info --}}
            <div class="shop-card-body">
                @if(optional($product->brand)->name || optional($product->category)->name)
                    <p class="shop-card-brand">{{ optional($product->brand)->name ?? optional($product->category)->name }}</p>
                @endif

                <a href="{{ route('product.show', $product->slug) }}" class="shop-card-title">
                    {{ $product->name }}
                </a>

                <div class="shop-card-price-row">
                    <span class="shop-card-price">Rs {{ number_format($effPrice, 2) }}</span>
                    @if($product->sale_price)
                        <span class="shop-card-price-old">Rs {{ number_format($product->base_price, 2) }}</span>
                    @endif
                </div>

                <div class="shop-card-actions">
                    <form action="{{ route('cart.add') }}" method="POST" style="flex:1;">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" class="shop-btn-cart w-100"
                            onclick="if({{ $hasVars ? 'true' : 'false' }}){ event.preventDefault(); window.location.href='{{ route('product.show', $product->slug) }}'; }">
                            <i class="bi bi-cart-plus"></i>
                            {{ $hasVars ? 'Options' : 'Add to Cart' }}
                        </button>
                    </form>

                    <form action="{{ route('wishlist.toggle') }}" method="POST">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <button type="submit" class="shop-btn-icon {{ $isWishlisted ? 'is-wishlisted' : '' }}"
                            title="{{ $isWishlisted ? 'Wishlisted' : 'Wishlist' }}"
                            aria-label="{{ $isWishlisted ? 'Wishlisted' : 'Add to wishlist' }}">
                            <i class="bi {{ $isWishlisted ? 'bi-heart-fill' : 'bi-heart' }}"></i>
                        </button>
                    </form>

                    <button type="button" class="shop-btn-icon" title="Quick View"
                        onclick="openQuickView({{ $product->id }}, '{{ addslashes($product->name) }}')">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

        </div>
    </div>
@endforeach