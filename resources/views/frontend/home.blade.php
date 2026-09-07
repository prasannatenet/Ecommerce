@extends('layouts.frontend')

@section('title', ($appSetting->site_name ?? 'GEHNA') . ' | Fine Jewellery')

@section('content')
    <section class="category-grid-section py-5" id="categoryGrid">
        <div class="container-fluid px-4 px-xl-5">
            <div class="pm-section-header text-center mb-4">
                <span class="pm-section-sub">Discover Your Favorites</span>
                <h2 class="pm-section-title">Explore By Category</h2>
                <div class="pm-title-line mx-auto"></div>
            </div>

            <div class="row g-4">
                @forelse ($categories->take(4) as $category)
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="{{ route('category.show', $category->slug ?? $category->id) }}" class="text-decoration-none">
                            <div class="cat-grid-item h-100">
                                @if ($category->image)
                                    <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}" loading="lazy">
                                @else
                                    <img src="{{ asset('frontend/images/about1.png') }}" alt="{{ $category->name }}" loading="lazy">
                                @endif
                                <span class="cat-grid-label">{{ strtoupper($category->name) }}</span>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12 text-center py-5 text-muted">Categories will appear here soon.</div>
                @endforelse
            </div>

            <div class="text-center mt-5">
                <a href="{{ route('categories.index') }}" class="category-more-button">See More Categories <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <section class="pm-products-section py-5" id="productCategorySection">
        <div class="container-fluid px-4 px-xl-5">
            <div class="pm-section-header text-center mb-4">
                <span class="pm-section-sub">Discover The Latest Additions</span>
                <h2 class="pm-section-title">Our Jewellery</h2>
                <div class="pm-title-line mx-auto"></div>
            </div>

            @if ($featuredProducts->isEmpty())
                <p class="text-center text-muted py-5">New jewellery is coming soon.</p>
            @else
                <div class="pm-products-carousel">
                    <button type="button" class="pm-products-nav pm-products-nav-prev" id="pmProductsPrev" aria-label="Previous products">
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <div class="pm-products-scroller" id="pmProductsScroller" tabindex="0" aria-label="Featured jewellery carousel">
                        <div class="pm-products-track">
                            @foreach ($featuredProducts->take(8) as $product)
                                <div class="pm-product-slide">
                                    @include('frontend.partials.product-card', ['product' => $product])
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <button type="button" class="pm-products-nav pm-products-nav-next" id="pmProductsNext" aria-label="Next products">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            @endif

            <div class="text-center mt-5">
                <a href="{{ route('products.index') }}" class="category-more-button">Shop All Creations <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    (() => {
        const scroller = document.getElementById('pmProductsScroller');
        if (!scroller) return;

        const track = scroller.querySelector('.pm-products-track');
        const prevBtn = document.getElementById('pmProductsPrev');
        const nextBtn = document.getElementById('pmProductsNext');
        const wrap = scroller.closest('.pm-products-carousel');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        // Matches the CSS: 4 slides on desktop, 3 on tablet, 2 on mobile.
        const visibleCount = () => {
            if (window.matchMedia('(max-width: 767.98px)').matches) return 2;
            if (window.matchMedia('(max-width: 991.98px)').matches) return 3;
            return 4;
        };

        const updateNav = () => {
            const maxScroll = scroller.scrollWidth - scroller.clientWidth;
            const canScroll = maxScroll > 8;

            if (wrap) wrap.classList.toggle('no-scroll', !canScroll);
            if (prevBtn) prevBtn.classList.toggle('is-disabled', scroller.scrollLeft <= 8);
            if (nextBtn) nextBtn.classList.toggle('is-disabled', scroller.scrollLeft >= maxScroll - 8);
        };

        // Scroll exactly one "page" (as many cards as are visible).
        const page = (direction) => {
            const slide = track ? track.querySelector('.pm-product-slide') : null;
            if (!slide) return;

            const styles = getComputedStyle(track);
            const gap = parseFloat(styles.columnGap || styles.gap) || 0;
            const step = (slide.getBoundingClientRect().width + gap) * visibleCount();

            scroller.scrollBy({
                left: direction * step,
                behavior: reduceMotion.matches ? 'auto' : 'smooth',
            });
        };

        if (prevBtn) prevBtn.addEventListener('click', () => page(-1));
        if (nextBtn) nextBtn.addEventListener('click', () => page(1));
        scroller.addEventListener('scroll', updateNav, { passive: true });
        window.addEventListener('resize', updateNav);
        updateNav();

        // Drag to scroll with a mouse (touch devices scroll natively).
        let isDown = false;
        let dragged = false;
        let startX = 0;
        let startScrollLeft = 0;

        scroller.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse') return;
            isDown = true;
            dragged = false;
            startX = event.clientX;
            startScrollLeft = scroller.scrollLeft;
            scroller.classList.add('is-dragging');
        });

        window.addEventListener('pointermove', (event) => {
            if (!isDown) return;
            const dx = event.clientX - startX;
            if (Math.abs(dx) > 6) dragged = true;
            if (dragged) scroller.scrollLeft = startScrollLeft - dx;
        });

        window.addEventListener('pointerup', () => {
            if (!isDown) return;
            isDown = false;
            scroller.classList.remove('is-dragging');
        });

        // Swallow the click that follows a real drag so links/buttons don't fire.
        scroller.addEventListener('click', (event) => {
            if (dragged) {
                event.preventDefault();
                event.stopPropagation();
                dragged = false;
            }
        }, true);
    })();
</script>
@endpush
