
@php
    $heroSlides = isset($sliders) && $sliders->count()
        ? $sliders->filter(fn ($slide) => filled($slide->image_path))->values()
        : collect();

    if ($heroSlides->isEmpty()) {
        $heroSlides = collect([
            (object) [
                'image_path' => null,
                'title' => 'Featured jewellery collection',
                'button_link' => null,
            ],
        ]);
    }
@endphp

@push('styles')
<style>
    /* =========================================
    PROMOTIONAL HERO
    ========================================= */

    .promo-hero-slider {
        position: relative;
        width: 100%;
        aspect-ratio: 1920 / 445;
        min-height: 0;
        overflow: hidden;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 72%, #064e3b 100%);
    }

    .promo-hero-slider .hero-swiper,
    .promo-hero-slider .swiper-wrapper,
    .promo-hero-slider .swiper-slide {
        width: 100%;
        height: 100%;
    }

    .promo-hero-slider .swiper-slide {
        position: relative;
        overflow: hidden;
    }

    /* Slide link takes complete hero area */
    .promo-hero-slide-link {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    /* =========================================
       IMAGE FULL COVER
    ========================================= */

    .promo-hero-slide-image {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center center;
    }

    .promo-hero-slider .swiper-slide > .promo-hero-slide-image {
        z-index: 1;
    }

    /* =========================================
       ACCESSIBILITY
    ========================================= */

    .promo-hero-slide-link:focus-visible {
        outline: 3px solid #fff;
        outline-offset: -5px;
    }

    /* =========================================
       NAVIGATION ARROWS
    ========================================= */

    .promo-hero-slider .swiper-button-prev,
    .promo-hero-slider .swiper-button-next {
        width: 44px;
        height: 44px;

        border-radius: 50%;

        background: rgba(255, 255, 255, 0.90);
        color: #0f172a;

        border: 1px solid rgba(212, 175, 55, 0.35);

        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);

        transition:
            transform 0.2s ease,
            background 0.2s ease;

        z-index: 10;
    }

    .promo-hero-slider .swiper-button-prev:hover,
    .promo-hero-slider .swiper-button-next:hover {
        background: #e7c96b;
        color: #0f172a;
        transform: scale(1.06);
    }

    .promo-hero-slider .swiper-button-prev::after,
    .promo-hero-slider .swiper-button-next::after {
        font-size: 16px;
        font-weight: 800;
    }

    /* =========================================
       PAGINATION
    ========================================= */

    .promo-hero-slider .swiper-pagination {
        bottom: 18px;
        z-index: 10;
    }

    .promo-hero-slider .swiper-pagination-bullet {
        width: 8px;
        height: 8px;

        background: rgba(255, 255, 255, 0.75);
        opacity: 1;

        transition:
            width 0.3s ease,
            background 0.3s ease;
    }

    .promo-hero-slider .swiper-pagination-bullet-active {
        width: 24px;
        border-radius: 8px;
        background: #d4af37;
    }

    /* =========================================
       TABLET
    ========================================= */

    @media (max-width: 991px) {
        .promo-hero-slider {
            aspect-ratio: 1920 / 520;
        }
    }

    /* =========================================
       MOBILE
    ========================================= */

    @media (max-width: 767px) {

        .promo-hero-slider {
            aspect-ratio: 4 / 3;
        }

        .promo-hero-slider .swiper-button-prev,
        .promo-hero-slider .swiper-button-next {
            width: 34px;
            height: 34px;
        }

        .promo-hero-slider .swiper-button-prev::after,
        .promo-hero-slider .swiper-button-next::after {
            font-size: 12px;
        }

        .promo-hero-slider .swiper-button-prev {
            left: 10px;
        }

        .promo-hero-slider .swiper-button-next {
            right: 10px;
        }

        .promo-hero-slider .swiper-pagination {
            bottom: 12px;
        }
    }

    /* =========================================
       SMALL MOBILE
    ========================================= */

    @media (max-width: 480px) {
        .promo-hero-slider {
            aspect-ratio: 4 / 3;
        }
    }
</style>
@endpush


<section class="promo-hero-slider" aria-label="Featured offers">

    <div class="swiper hero-swiper">

        <div class="swiper-wrapper">

            @foreach ($heroSlides as $slide)

                @php
                    $slideImage = filled($slide->image_path)
                        ? asset('storage/' . ltrim($slide->image_path, '/'))
                        : asset('frontend/images/about1.png');

                    $slideLink = filled($slide->button_link ?? null)
                        ? $slide->button_link
                        : null;
                @endphp

                <div class="swiper-slide">

                    @if ($slideLink)
                        <a href="{{ $slideLink }}"
                           class="promo-hero-slide-link">
                    @endif

                        <img
                            src="{{ $slideImage }}"
                            alt="{{ $slide->title ?? 'Featured offer' }}"
                            class="promo-hero-slide-image"
                        >

                    @if ($slideLink)
                        </a>
                    @endif

                </div>

            @endforeach

        </div>

        @if ($heroSlides->count() > 1)

            <div class="swiper-button-prev"
                 aria-label="Previous slide">
            </div>

            <div class="swiper-button-next"
                 aria-label="Next slide">
            </div>

            <div class="swiper-pagination"></div>

        @endif

    </div>

</section>

