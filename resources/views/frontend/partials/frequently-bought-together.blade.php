@if(! empty($frequentlyBoughtTogether) && $frequentlyBoughtTogether->isNotEmpty())
<section class="frequently-bought-section" id="frequentlyBoughtTogether">
    <div class="frequently-bought-heading">
        <span class="frequently-bought-badge"><i class="bi bi-bag-heart me-1"></i> Based on real purchases</span>
        <h2>
            Frequently Bought Together
            <span class="frequently-bought-count">{{ $frequentlyBoughtTogether->count() }} suggestion{{ $frequentlyBoughtTogether->count() > 1 ? 's' : '' }}</span>
        </h2>
        <p class="frequently-bought-copy">Customers who bought this product also commonly purchased these items.</p>
    </div>

    <div class="row g-4">
        @include('frontend.partials.product-cards', [
            'products' => $frequentlyBoughtTogether,
            'wishlistProductIds' => $frequentlyBoughtTogetherWishlistIds ?? [],
        ])
    </div>
</section>

<style>
    .frequently-bought-section { padding: 48px 0 24px; }
    .frequently-bought-heading { margin-bottom: 26px; }
    .frequently-bought-badge {
        display: inline-block;
        background: linear-gradient(135deg, #013a3c, #02AAB1);
        color: #fff;
        font-weight: 800;
        font-size: 0.8rem;
        letter-spacing: 1.5px;
        padding: 8px 18px;
        border-radius: 999px;
        text-transform: uppercase;
    }
    .frequently-bought-heading h2 { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 1.5rem; font-weight: 700; color: #013a3c; margin: 14px 0 0; }
    .frequently-bought-count { font-size: 0.72rem; font-weight: 700; color: #017075; background: rgba(2, 170, 177, 0.1); padding: 3px 10px; border-radius: 999px; }
    .frequently-bought-copy { color: #6c757d; margin: 8px 0 0; font-size: 0.92rem; }
</style>
@endif
