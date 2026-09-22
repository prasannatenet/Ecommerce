@if(! empty($combos) && $combos->isNotEmpty())
@php
    $productImage = fn ($p) => $p->primary_image ?? optional($p->images->first())->path;
@endphp
<style>
    .combo-offers-section { padding: 40px 0 10px; }
    .combo-offers-heading { display: flex; align-items: center; gap: 12px; margin-bottom: 22px; flex-wrap: wrap; }
    .combo-offers-heading .coh-badge {
        background: linear-gradient(135deg, #013a3c, #02AAB1);
        color: #fff; font-weight: 800; font-size: 0.8rem; letter-spacing: 1.5px;
        padding: 8px 18px; border-radius: 999px; text-transform: uppercase;
    }
    .combo-offers-heading h2 { font-size: 1.5rem; font-weight: 700; margin: 0; color: #013a3c; }
    .combo-offer-card {
        border: 1.5px solid rgba(2, 170, 177, 0.4);
        border-radius: 16px; background: #fff;
        box-shadow: 0 4px 18px rgba(1, 58, 60, 0.08);
        padding: 20px 22px; margin-bottom: 18px;
    }
    .combo-offer-card .co-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
    .combo-offer-card .co-ribbon {
        background: #b91c1c; color: #fff; font-size: 0.7rem; font-weight: 800;
        letter-spacing: 1px; padding: 4px 10px; border-radius: 6px; text-transform: uppercase;
    }
    .combo-offer-card .co-name { font-size: 1.08rem; font-weight: 700; color: #013a3c; }
    .combo-offer-card .co-desc { font-size: 0.85rem; color: #64748b; }
    .combo-offer-card .co-products { display: flex; align-items: stretch; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .combo-offer-card .co-product {
        display: flex; align-items: center; gap: 10px;
        border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px 12px;
        background: #f8fafc; min-width: 190px; max-width: 240px; flex: 1 1 190px;
        text-decoration: none; color: inherit; transition: box-shadow .15s ease, border-color .15s ease;
    }
    .combo-offer-card .co-product:hover { border-color: #02AAB1; box-shadow: 0 3px 12px rgba(2, 170, 177, 0.18); }
    .combo-offer-card .co-product.this-item { border-color: #02AAB1; background: rgba(2, 170, 177, 0.06); }
    .combo-offer-card .co-product img {
        width: 52px; height: 52px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: #fff;
    }
    .combo-offer-card .co-product .co-p-name {
        display: block; font-size: 0.82rem; font-weight: 600; color: #0f172a;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 150px;
    }
    .combo-offer-card .co-product .co-p-price { font-size: 0.8rem; font-weight: 700; color: #017075; }
    .combo-offer-card .co-product .co-p-tag {
        display: inline-block; font-size: 0.62rem; font-weight: 800; letter-spacing: 0.5px;
        background: #02AAB1; color: #fff; padding: 1px 7px; border-radius: 999px; text-transform: uppercase;
    }
    .combo-offer-card .co-plus { display: flex; align-items: center; font-weight: 800; color: #017075; font-size: 1.2rem; }
    .combo-offer-card .co-pricing {
        display: flex; flex-wrap: wrap; align-items: center; gap: 14px;
        background: rgba(2, 170, 177, 0.06); border-radius: 12px; padding: 12px 16px;
    }
    .combo-offer-card .co-old { text-decoration: line-through; color: #64748b; font-weight: 600; }
    .combo-offer-card .co-discount-badge {
        background: #b91c1c; color: #fff; font-weight: 800; font-size: 0.78rem;
        padding: 4px 12px; border-radius: 999px;
    }
    .combo-offer-card .co-price-label { font-size: 0.8rem; color: #475569; font-weight: 600; }
    .combo-offer-card .co-price { font-size: 1.35rem; font-weight: 800; color: #017075; }
    .combo-offer-card .co-save { color: #15803d; font-weight: 700; font-size: 0.82rem; }
    .combo-offer-card .co-valid { font-size: 0.75rem; color: #64748b; }
</style>

<section class="combo-offers-section" id="comboOffers">
    <div class="combo-offers-heading">
        <span class="coh-badge"><i class="bi bi-gift-fill me-1"></i> Combo Offers</span>
        <h2>Buy together &amp; save more</h2>
    </div>

    @foreach($combos as $combo)
        <div class="combo-offer-card">
            <div class="co-head">
                <span class="co-ribbon">Combo Offer</span>
                <div>
                    <div class="co-name">{{ $combo->name }}</div>
                    @if($combo->description)
                        <div class="co-desc">{{ $combo->description }}</div>
                    @endif
                </div>
            </div>

            <div class="co-products">
                @foreach($combo->products as $comboProduct)
                    @if(! $loop->first)
                        <span class="co-plus"><i class="bi bi-plus-lg"></i></span>
                    @endif
                    <a href="{{ route('product.show', $comboProduct->slug) }}"
                       class="co-product {{ $comboProduct->id === $product->id ? 'this-item' : '' }}">
                        @if($productImage($comboProduct))
                            <img src="{{ asset('storage/' . $productImage($comboProduct)) }}" alt="{{ $comboProduct->name }}">
                        @else
                            <img src="{{ asset('frontend/images/dumbbell.png') }}" alt="{{ $comboProduct->name }}">
                        @endif
                        <span>
                            @if($comboProduct->id === $product->id)
                                <span class="co-p-tag">This item</span>
                            @endif
                            <span class="co-p-name" title="{{ $comboProduct->name }}">{{ $comboProduct->name }}</span>
                            <span class="co-p-price">₹{{ number_format((float) $comboProduct->display_price, 2) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="co-pricing">
                <span class="co-old">₹{{ number_format($combo->productsTotal(), 2) }}</span>
                <span class="co-discount-badge">
                    @if($combo->discount_type === 'percent')
                        {{ rtrim(rtrim(number_format($combo->discount_value, 2), '0'), '.') }}% OFF
                    @else
                        ₹{{ number_format($combo->discount_value, 2) }} OFF
                    @endif
                </span>
                <span class="co-price-label">All {{ $combo->products->count() }} together:</span>
                <span class="co-price">₹{{ number_format($combo->comboPrice(), 2) }}</span>
                <span class="co-save"><i class="bi bi-tag-fill me-1"></i>You save ₹{{ number_format($combo->discountAmount(), 2) }} ({{ $combo->savingsPercent() }}%)</span>
                @if($combo->expires_at)
                    <span class="co-valid ms-auto"><i class="bi bi-clock me-1"></i>Offer ends {{ $combo->expires_at->format('M d, Y') }}</span>
                @endif
            </div>

            <div class="co-actions mt-3">
                <form action="{{ route('cart.add-combo') }}" method="POST" class="d-inline-flex align-items-center gap-2">
                    @csrf
                    <input type="hidden" name="combo_id" value="{{ $combo->id }}">
                    <label for="combo_qty_{{ $combo->id }}" class="co-price-label mb-0">Qty:</label>
                    <input type="number" name="quantity" id="combo_qty_{{ $combo->id }}" value="1" min="1" max="99"
                           class="form-control form-control-sm" style="width: 70px;">
                    <button type="submit" class="btn btn-sm px-4 py-2" style="background: linear-gradient(135deg, #013a3c, #02AAB1); color: #fff; border: none; font-weight: 700; border-radius: 999px;">
                        <i class="bi bi-cart-plus me-1"></i>Add to Cart
                    </button>
                </form>
            </div>
        </div>
    @endforeach
</section>
@endif
