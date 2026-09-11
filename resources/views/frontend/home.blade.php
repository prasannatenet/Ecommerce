@extends('layouts.frontend')

@section('title', ($appSetting->site_name ?? 'GEHNA') . ' | Fine Jewellery')

@section('content')
    <section class="category-grid-section py-5" id="categoryGrid">
        <div class="container px-4 px-xl-5">
            <div class="pm-section-header text-center mb-4">
                <span class="pm-section-sub">Discover Your Favorites</span>
                <h2 class="pm-section-title">Explore By Category</h2>
                <div class="pm-title-line mx-auto"></div>
            </div>

            <div class="cagtegory-grid">
                @forelse ($categories->take(10) as $category)
                    <div class="category-grid-card">
                        <a class="category_card" href="{{ route('category.show', $category->slug ?? $category->id) }}"
                            class="text-decoration-none">
                            <div class="g-category-image">
                                @if ($category->image)
                                    <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}"
                                        loading="lazy">
                                @else
                                    <img src="{{ asset('frontend/images/about1.png') }}" alt="{{ $category->name }}" loading="lazy">
                                @endif
                            </div>
                            <h5>{{ strtoupper($category->name) }}</h5>
                        </a>
                    </div>
                @empty
                    <div class="col-12 text-center py-5 text-muted">Categories will appear here soon.</div>
                @endforelse
            </div>

            <div class="text-center">
                <a href="{{ route('categories.index') }}" class="category-more-button">See More Categories <i
                        class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <section>
        <section class="featured-section">
            <div class="featured-grid">
                <!-- LEFT LARGE CARD -->
                <div class="featured-left-section">
                    <div class="featured-content">
                        <h2>{{ $featuredSection?->title ?? 'Earring Collection' }}</h2>
                        <p>{{ $featuredSection?->subtext ?? 'Discover our latest collection of handcrafted jewellery.' }}</p>
                        <div class="text-center mt-4">
                            <a href="{{ $featuredSection?->cta_link ?? url('/categories') }}" class="category-more-button">{{ $featuredSection?->cta_text ?? 'Explore Collection' }} <i
                                    class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                                         <video src="{{ $featuredSection?->video_url ?? asset('frontend/assets/jewellery.mp4') }}" autoplay muted loop poster="{{ $featuredSection?->poster_image_url ?? '' }}"></video>
                </div>


                <div class="featured-right-section">

                    <!-- RIGHT TOP CARD -->
                    <div>
                        <img class="ad-img" src="{{ $featuredSection?->poster_image_url ?? asset('frontend/assets/ad_1.png') }}" alt="Featured ad">
                    </div>
    
                    <!-- RIGHT BOTTOM CARD -->
                    <div class="featured-small">
                        @if ($featuredProducts->isEmpty())
                            <p class="text-center text-muted py-5">New jewellery is coming soon.</p>
                        @else
                            <div class="pm-products-carousel pm-earring-products-carousel">
                                <button type="button" class="pm-products-nav pm-products-nav-prev" id="earringProductsPrev"
                                    aria-label="Previous products">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
    
                                <div class="pm-products-scroller" id="earringProductsScroller" data-visible-count="2" tabindex="0"
                                    aria-label="Featured jewellery carousel">
                                    <div class="pm-products-track">
                                        @foreach ($featuredProducts->take(8) as $product)
                                            <div class="pm-product-slide">
                                                @include('frontend.partials.product-card', ['product' => $product])
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
    
                                <button type="button" class="pm-products-nav pm-products-nav-next" id="earringProductsNext"
                                    aria-label="Next products">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </section>
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
                    <button type="button" class="pm-products-nav pm-products-nav-prev" id="pmProductsPrev"
                        aria-label="Previous products">
                        <i class="bi bi-chevron-left"></i>
                    </button>

                    <div class="pm-products-scroller" id="pmProductsScroller" tabindex="0"
                        aria-label="Featured jewellery carousel">
                        <div class="pm-products-track">
                            @foreach ($featuredProducts->take(8) as $product)
                                <div class="pm-product-slide">
                                    @include('frontend.partials.product-card', ['product' => $product])
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <button type="button" class="pm-products-nav pm-products-nav-next" id="pmProductsNext"
                        aria-label="Next products">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            @endif

            <div class="text-center mt-5">
                <a href="{{ route('products.index') }}" class="category-more-button">Shop All Creations <i
                        class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </section>


    <!-- Large Image section -->
    <section class="g-section-large">
        <!-- LEFT -->
        <div class="g-section-image">
            <div class="g-sticky-image">
                <img src="{{ $largeImageSection?->large_image_url ?? asset('frontend/assets/ring-collection.png') }}" alt="Ring Collection">
            </div>
        </div>
        <div class="g-section-products">
            @if ($featuredProducts->isEmpty())
                <p class="text-center text-muted py-5">New jewellery is coming soon.</p>
            @else
                @foreach ($featuredProducts->take(8) as $product)
                    <div class="pm-product-slide">
                        @include('frontend.partials.product-card', ['product' => $product])
                    </div>
                @endforeach
            @endif
        </div>
    </section>

    <!-- INSTA SECTION -->
    <section class="g-instagram-section">
        <div class="g-instagram-heading">
            <span class="g-instagram-eyebrow">FOLLOW THE SPARKLE</span>
            <h2>Our World on <span>Instagram</span></h2>
        </div>

        <div class="g-instagram-slider-wrap">
            <button class="g-insta-arrow g-insta-prev" type="button">&#8592;</button>
            <div class="g-instagram-slider" id="instagramSlider">
                <!-- IMAGE -->
                <a href="#" class="g-insta-card">
                    <img src="{{ asset('frontend/assets/insta-1.jpg') }}" alt="Jewellery" loading="lazy">
                    <div class="g-insta-overlay"><span>Instagram</span></div>
                </a>

                <!-- IMAGE -->
                <a href="#" class="g-insta-card">
                    <img src="{{ asset('frontend/assets/insta-2.jpg') }}" alt="Jewellery" loading="lazy">
                    <div class="g-insta-overlay"><span>Instagram</span></div>
                </a>


                <!-- IMAGE -->
                <a href="#" class="g-insta-card">
                    <img src="{{ asset('frontend/assets/insta-3.jpg') }}" alt="Ring Collection" loading="lazy">
                    <div class="g-insta-overlay">
                        <span>Instagram</span>
                    </div>

                </a>


                <!-- IMAGE -->
                <a href="#" class="g-insta-card">
                    <img src="{{ asset('frontend/assets/insta-4.jpg') }}" alt="Diamond Jewellery" loading="lazy">
                    <div class="g-insta-overlay">
                        <span>Instagram</span>
                    </div>
                </a>
                <!-- IMAGE -->
                <a href="#" class="g-insta-card">
                    <img src="{{ asset('frontend/assets/insta-5.jpg') }}" alt="Diamond Jewellery" loading="lazy">
                    <div class="g-insta-overlay">
                        <span>Instagram</span>
                    </div>
                </a>
                <!-- IMAGE -->
                <a href="#" class="g-insta-card">
                    <img src="{{ asset('frontend/assets/insta-6.jpg') }}" alt="Diamond Jewellery" loading="lazy">
                    <div class="g-insta-overlay">
                        <span>Instagram</span>
                    </div>
                </a>
                <!-- IMAGE -->
                <a href="#" class="g-insta-card">
                    <img src="{{ asset('frontend/assets/insta-7.jpg') }}" alt="Diamond Jewellery" loading="lazy">
                    <div class="g-insta-overlay">
                        <span>Instagram</span>
                    </div>
                </a>
                <!-- IMAGE -->
                <a href="#" class="g-insta-card">
                    <img src="{{ asset('frontend/assets/insta-8.jpg') }}" alt="Diamond Jewellery" loading="lazy">
                    <div class="g-insta-overlay">
                        <span>Instagram</span>
                    </div>
                </a>
            </div>

            <button class="g-insta-arrow g-insta-next" type="button">
                &#8594;
            </button>
        </div>

        <div class="g-instagram-follow">
            <a href="#" class="g-instagram-follow-btn">
                <span>◎</span>
                Follow @gehna
            </a>
        </div>
    </section>

@endsection

@push('scripts')
    <script>
        (() => {
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

            document.querySelectorAll('.pm-products-scroller').forEach((scroller) => {
                const track = scroller.querySelector('.pm-products-track');
                const wrap = scroller.closest('.pm-products-carousel');
                const prevBtn = wrap?.querySelector('.pm-products-nav-prev');
                const nextBtn = wrap?.querySelector('.pm-products-nav-next');
                const configuredVisibleCount = Number(scroller.dataset.visibleCount) || 4;
                const isEarringCarousel = wrap?.classList.contains('pm-earring-products-carousel');

                const visibleCount = () => {
                    if (window.matchMedia('(max-width: 767.98px)').matches) return 1;
                    return configuredVisibleCount;
                };

                const updateNav = () => {
                    const maxScroll = scroller.scrollWidth - scroller.clientWidth;
                    const canScroll = maxScroll > 8;

                    if (wrap) wrap.classList.toggle('no-scroll', !canScroll);
                    if (prevBtn) prevBtn.classList.toggle('is-disabled', scroller.scrollLeft <= 8);
                    if (nextBtn) nextBtn.classList.toggle('is-disabled', scroller.scrollLeft >= maxScroll - 8);
                };

                const page = (direction) => {
                    const slide = track?.querySelector('.pm-product-slide');
                    if (!slide) return;

                    const styles = getComputedStyle(track);
                    const gap = parseFloat(styles.columnGap || styles.gap) || 0;
                    const step = (slide.getBoundingClientRect().width + gap) * visibleCount();

                    scroller.scrollBy({
                        left: direction * step,
                        behavior: reduceMotion.matches ? 'auto' : 'smooth',
                    });
                };

                prevBtn?.addEventListener('click', () => page(-1));
                nextBtn?.addEventListener('click', () => page(1));
                scroller.addEventListener('scroll', updateNav, { passive: true });
                window.addEventListener('resize', updateNav);

                if (isEarringCarousel && !reduceMotion.matches) {
                    let autoPlay;

                    const stopAutoPlay = () => {
                        if (autoPlay) clearInterval(autoPlay);
                    };

                    const startAutoPlay = () => {
                        stopAutoPlay();
                        autoPlay = setInterval(() => {
                            const maxScroll = scroller.scrollWidth - scroller.clientWidth;

                            if (scroller.scrollLeft >= maxScroll - 8) {
                                scroller.scrollTo({ left: 0, behavior: 'smooth' });
                            } else {
                                page(1);
                            }
                        }, 3500);
                    };

                    scroller.addEventListener('mouseenter', stopAutoPlay);
                    scroller.addEventListener('mouseleave', startAutoPlay);
                    scroller.addEventListener('focusin', stopAutoPlay);
                    scroller.addEventListener('focusout', startAutoPlay);
                    startAutoPlay();
                }

                updateNav();
            });
        })();


        //------------------------------------

        document.addEventListener("DOMContentLoaded", function () {
            const cards = document.querySelectorAll(".category-grid-card");
            const observer = new IntersectionObserver(
                (entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.setAttribute(
                                "data-inview",
                                "true"
                            );
                            observer.unobserve(entry.target);
                        }
                    });
                },
                {
                    threshold: 0.15,
                    rootMargin: "0px 0px -50px 0px"
                }
            );

            cards.forEach(card => {
                observer.observe(card);
            });
        });



        // INSTA SCROLLER

        /* =========================================
    INSTAGRAM SLIDER
    ========================================= */

        document.addEventListener('DOMContentLoaded', function () {
            const slider = document.getElementById('instagramSlider');
            if (!slider) return;
            const nextBtn = document.querySelector('.g-insta-next');
            const prevBtn = document.querySelector('.g-insta-prev');
            const cards = slider.querySelectorAll('.g-insta-card');
            if (!cards.length) return;

            /* =====================================
               SLIDE WIDTH
            ===================================== */

            function getScrollAmount() {
                const card = cards[0];
                const cardWidth = card.offsetWidth;
                const gap = parseFloat(
                    getComputedStyle(slider).gap
                ) || 0;

                return cardWidth + gap;
            }


            /* =====================================
               NEXT
            ===================================== */

            nextBtn?.addEventListener('click', function () {
                slider.scrollBy({
                    left: getScrollAmount(),
                    behavior: 'smooth'
                });
            });


            /* =====================================
               PREVIOUS
            ===================================== */

            prevBtn?.addEventListener('click', function () {
                slider.scrollBy({
                    left: -getScrollAmount(),
                    behavior: 'smooth'
                });

            });


            /* =====================================
               AUTO SCROLL
            ===================================== */

            let autoScroll;
            function startAutoScroll() {
                stopAutoScroll();
                autoScroll = setInterval(function () {
                    const maxScroll =
                        slider.scrollWidth - slider.clientWidth;

                    /*
                     * Last slide reached
                     */
                    if (slider.scrollLeft >= maxScroll - 5) {

                        slider.scrollTo({
                            left: 0,
                            behavior: 'smooth'
                        });

                    } else {

                        slider.scrollBy({
                            left: getScrollAmount(),
                            behavior: 'smooth'
                        });

                    }
                }, 3500);
            }


            function stopAutoScroll() {

                if (autoScroll) {
                    clearInterval(autoScroll);
                }

            }

            /* =====================================
               PAUSE ON HOVER
            ===================================== */

            slider.addEventListener('mouseenter', stopAutoScroll);
            slider.addEventListener('mouseleave', startAutoScroll);

            /* =====================================
               TOUCH / DRAG
            ===================================== */

            slider.addEventListener('touchstart', stopAutoScroll, {
                passive: true
            });

            slider.addEventListener('touchend', startAutoScroll, {
                passive: true
            });

            /* =====================================
               START
            ===================================== */

            startAutoScroll();
        });
    </script>
@endpush