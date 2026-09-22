@if(! empty($relatedProducts) && $relatedProducts->isNotEmpty())
<section class="related-products-section" id="relatedProducts">
    <div class="related-products-heading">
        <span class="rph-badge"><i class="bi bi-stars me-1"></i> Suggested For You</span>
        <h2>
            More From {{ optional($relatedProducts->first()->category)->name ?? 'This Category' }}
            <span class="rph-count">{{ $relatedProducts->count() }} item{{ $relatedProducts->count() > 1 ? 's' : '' }}</span>
        </h2>
    </div>

    <div class="row g-4">
        @include('frontend.partials.product-cards', [
            'products' => $relatedProducts,
            'wishlistProductIds' => $wishlistProductIds ?? [],
        ])
    </div>

    <div class="rp-view-all">
        <a href="{{ route('products.index', ['category' => $relatedProducts->first()->category_id ?? null]) }}"
           class="btn btn-sm px-4 py-2 rp-view-all-btn">
            View All In This Category
            <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
</section>

<style>
    .related-products-section { padding: 48px 0 24px; }
    .related-products-heading { display: flex; align-items: center; gap: 12px; margin-bottom: 26px; flex-wrap: wrap; }
    .related-products-heading .rph-badge {
        background: linear-gradient(135deg, #013a3c, #02AAB1);
        color: #fff; font-weight: 800; font-size: 0.8rem; letter-spacing: 1.5px;
        padding: 8px 18px; border-radius: 999px; text-transform: uppercase;
    }
    .related-products-heading h2 { font-size: 1.5rem; font-weight: 700; margin: 0; color: #013a3c; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .related-products-heading .rph-count {
        font-size: 0.72rem; font-weight: 700; color: #017075;
        background: rgba(2, 170, 177, 0.1); padding: 3px 10px; border-radius: 999px;
    }
    .rp-view-all { text-align: center; margin-top: 28px; }
    .rp-view-all-btn {
        background: #fff; color: #017075; border: 1.5px solid #02AAB1;
        font-weight: 700; border-radius: 999px; text-decoration: none;
        transition: all .15s ease;
    }
    .rp-view-all-btn:hover { background: linear-gradient(135deg, #013a3c, #02AAB1); color: #fff; border-color: transparent; }
</style>
@endif