{{-- Quick View Modal Body --}}
@php
    $activeVariations = $product->variations->where('is_active', true)->values();
    $mainImagePath = optional($product->images->first())->path ?? optional($product->images->first())->image_path ?? null;
    $mainImageUrl = $mainImagePath ? asset('storage/' . ltrim($mainImagePath, '/')) : null;
    $mainQuickViewImageId = 'quick-view-main-image-' . $product->id;
@endphp

<div class="row g-4">
    {{-- Product Image --}}
    <div class="col-md-5">
        @if($product->images->isNotEmpty())
            <img id="{{ $mainQuickViewImageId }}"
                 src="{{ $mainImageUrl }}"
                 alt="{{ $product->name }}"
                 style="width:100%; border-radius:10px; object-fit:contain; background:#f9f9f9; padding:16px; max-height:320px;">
            @if($product->images->count() > 1)
                <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;">
                    @foreach($product->images->take(4) as $img)
                        @php
                            $thumbPath = $img->path ?? $img->image_path ?? null;
                            $thumbUrl = $thumbPath ? asset('storage/' . ltrim($thumbPath, '/')) : null;
                        @endphp
                        @continue(!$thumbUrl)
                        <img src="{{ $thumbUrl }}"
                             alt="{{ $product->name }}"
                             style="width:60px; height:60px; object-fit:contain; border-radius:6px; background:#f9f9f9; padding:4px; border:1.5px solid #DEE2E6; cursor:pointer;"
                             onclick="document.getElementById('{{ $mainQuickViewImageId }}').src = this.src;">
                    @endforeach
                </div>
            @endif
        @else
            <div style="width:100%; height:240px; display:flex; align-items:center; justify-content:center; background:#f9f9f9; border-radius:10px; color:#ccc;">
                <i class="bi bi-image" style="font-size:3rem;"></i>
            </div>
        @endif
    </div>

    {{-- Product Info --}}
    <div class="col-md-7">
        @if(optional($product->brand)->name)
            <p style="color:#017075; font-size:0.75rem; text-transform:uppercase; letter-spacing:2px; font-weight:700; margin-bottom:4px;">{{ $product->brand->name }}</p>
        @endif
        <h4 style="font-size:1.2rem; font-weight:900; color:#0D0D0D; margin-bottom:8px;">{{ $product->name }}</h4>

        @if(optional($product->category)->name)
            <span style="display:inline-block; padding:3px 10px; background:rgba(1,112,117,0.1); color:#017075; font-size:0.78rem; font-weight:600; border-radius:20px; margin-bottom:12px;">
                {{ $product->category->name }}
            </span>
        @endif

        {{-- Price --}}
        <div style="margin-bottom:16px;">
            @if($product->sale_price)
                <span style="font-size:1.6rem; font-weight:900; color:#017075;">Rs {{ number_format($product->sale_price, 2) }}</span>
                <span style="font-size:1rem; color:#aaa; text-decoration:line-through; margin-left:8px;">Rs {{ number_format($product->base_price, 2) }}</span>
                @php
                    $base = (float) $product->base_price;
                    $sale = (float) $product->sale_price;
                    $disc = $base > 0 ? round((($base - $sale) / $base) * 100) : 0;
                @endphp
                <span style="display:inline-block; padding:2px 8px; background:#dc3545; color:#fff; font-size:0.75rem; font-weight:700; border-radius:4px; margin-left:8px;">-{{ $disc }}%</span>
            @else
                <span style="font-size:1.6rem; font-weight:900; color:#017075;">Rs {{ number_format($product->base_price, 2) }}</span>
            @endif
        </div>

        {{-- Short Description --}}
        @if($product->description)
            <p style="color:#6C757D; font-size:0.9rem; line-height:1.6; margin-bottom:16px; max-height:80px; overflow:hidden;">
                {{ Str::limit(strip_tags($product->description), 180) }}
            </p>
        @endif

        {{-- Variations --}}
        @if($activeVariations->isNotEmpty())
            <div style="margin-bottom:16px;">
                <label style="font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#6C757D; display:block; margin-bottom:8px;">Select Variation</label>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    @foreach($activeVariations as $variation)
                        @php
                            $variationAttributes = $variation->attributes ?? [];
                            if (is_string($variationAttributes)) {
                                $decodedVariationAttributes = json_decode($variationAttributes, true);
                                $variationAttributes = is_array($decodedVariationAttributes) ? $decodedVariationAttributes : [];
                            }
                            $label = collect($variationAttributes)->map(fn($v,$k) => ucfirst($k).': '.$v)->implode(' | ');
                            $label = $label ?: $variation->sku;
                        @endphp
                        <a href="{{ route('product.show', $product->slug) }}"
                           style="padding:6px 14px; border-radius:6px; border:1.5px solid #DEE2E6; font-size:0.82rem; font-weight:600; color:#495057; text-decoration:none; transition:all 0.2s;"
                           onmouseover="this.style.borderColor='#017075'; this.style.color='#017075';"
                           onmouseout="this.style.borderColor='#DEE2E6'; this.style.color='#495057';">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <p style="color:#f0ad4e; font-size:0.8rem; margin-top:8px;">
                    <i class="bi bi-info-circle me-1"></i> Select variation on product page to add to cart.
                </p>
            </div>
        @else
            {{-- Add to Cart --}}
            <form action="{{ route('cart.add') }}" method="POST" style="margin-bottom:10px;">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="btn-gehna btn-teal-gehna w-100 justify-content-center" style="border-radius:8px;">
                    <i class="bi bi-cart-plus me-2"></i> Add to Cart
                </button>
            </form>
        @endif

        {{-- Wishlist + View Full --}}
        <div style="display:flex; gap:8px; margin-top:6px;">
            <form action="{{ route('wishlist.toggle') }}" method="POST" style="flex:1;">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <button type="submit" style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #017075; background:#fff; color:#017075; font-size:0.9rem; font-weight:700; cursor:pointer; transition:all 0.2s;"
                        onmouseover="this.style.background='#017075'; this.style.color='#fff';"
                        onmouseout="this.style.background='#fff'; this.style.color='#017075';">
                    <i class="bi bi-heart me-1"></i> Wishlist
                </button>
            </form>
            <a href="{{ route('product.show', $product->slug) }}"
               style="flex:1; display:flex; align-items:center; justify-content:center; gap:6px; padding:10px; border-radius:8px; border:1.5px solid #DEE2E6; background:#fff; color:#6C757D; font-size:0.9rem; font-weight:700; text-decoration:none; transition:all 0.2s;"
               onmouseover="this.style.borderColor='#495057'; this.style.color='#495057';"
               onmouseout="this.style.borderColor='#DEE2E6'; this.style.color='#6C757D';">
                <i class="bi bi-box-arrow-up-right"></i> View Details
            </a>
        </div>
    </div>
</div>
