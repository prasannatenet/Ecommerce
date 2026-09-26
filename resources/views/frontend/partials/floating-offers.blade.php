{{--
    Floating Offer Tab
    ──────────────────
    A gold tab pinned to the left edge of every storefront page. It is
    position:fixed, so it travels with the shopper as they scroll, and it
    opens a panel listing every running coupon.

    $offerCoupons is supplied by the "frontend.partials.floating-offers"
    view composer in AppServiceProvider (see CouponService::offerSummaries).
--}}

@php
    $offerCount = $offerCoupons->count();
@endphp

<div class="offer-tab-wrap" id="offerTab">
    {{-- Tap-away shield, painted only while the panel is open --}}
    <div class="offer-tab-backdrop" id="offerTabBackdrop" hidden></div>

    <button type="button" class="offer-tab" id="offerTabBtn"
        aria-expanded="false" aria-controls="offerTabPanel"
        aria-label="Show {{ $offerCount }} available {{ \Illuminate\Support\Str::plural('coupon', $offerCount) }}">
        <span class="offer-tab-pct" aria-hidden="true">%</span>
        <span class="offer-tab-label" aria-hidden="true">Hot Offer</span>
        @if($offerCount > 0)
            <span class="offer-tab-count" aria-hidden="true">{{ $offerCount }}</span>
        @endif
    </button>

    <div class="offer-tab-panel" id="offerTabPanel" role="dialog" aria-modal="false"
        aria-label="Available coupons" aria-hidden="true">
        <div class="offer-panel-head">
            <div>
                <span class="offer-panel-kicker">Exclusive</span>
                <h2 class="offer-panel-title">Coupons &amp; Offers</h2>
            </div>
            <button type="button" class="offer-panel-close" id="offerTabClose" aria-label="Close offers">
                &times;
            </button>
        </div>

        <div class="offer-panel-body">
            @forelse($offerCoupons as $offer)
                <article class="offer-card {{ $offer['is_available'] ? '' : 'is-spent' }}">
                    <div class="offer-card-main">
                        <span class="offer-card-headline">{{ $offer['offer_text'] }}</span>
                        <span class="offer-card-code">{{ strtoupper($offer['code']) }}</span>
                        <span class="offer-card-meta">
                            @if($offer['min_order_amount'] > 0)
                                Min order ₹{{ number_format($offer['min_order_amount'], 0) }} ·
                            @endif
                            {{ $offer['validity_text'] }}
                        </span>
                        @if(! $offer['is_available'] && $offer['ineligible_reason'])
                            <span class="offer-card-reason">{{ $offer['ineligible_reason'] }}</span>
                        @endif
                    </div>

                    <button type="button" class="offer-card-copy" data-offer-code="{{ $offer['code'] }}"
                        {{ $offer['is_available'] ? '' : 'disabled' }}
                        aria-label="Copy coupon code {{ strtoupper($offer['code']) }}">
                        <i class="bi bi-clipboard me-1" aria-hidden="true"></i>Copy
                    </button>
                </article>
            @empty
                <p class="offer-panel-empty">
                    <i class="bi bi-tag me-1" aria-hidden="true"></i>
                    No coupons are running right now — check back soon.
                </p>
            @endforelse
        </div>

        <div class="offer-panel-foot">
            <a href="{{ route('cart.index') }}" class="offer-panel-cta">
                Apply on your cart <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</div>


<style>
    /* ===== FLOATING OFFER TAB ===== */
    .offer-tab-wrap {
        /*
         * Spans the viewport height so the tab is centred with flexbox. It must
         * not carry a transform: a transformed ancestor becomes the containing
         * block for position:fixed children, which would drag the panel and the
         * tap-away shield out of the viewport and anchor them to this strip.
         */
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        width: 44px;
        z-index: 1040;
        display: flex;
        align-items: center;
        font-family: inherit;
        /* The strip covers the page's left edge, so only the tab and the open
           panel may take pointer events. */
        pointer-events: none;
    }

    /* --- The tab itself (left-edge gold tab) --- */
    .offer-tab {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        width: 44px;
        padding: 16px 6px;
        border: none;
        border-radius: 0 26px 26px 0;
        background: linear-gradient(180deg, #f3d98b 0%, #d4af37 45%, #a67c00 100%);
        color: #8b1a1a;
        box-shadow: 0 6px 22px rgba(120, 82, 0, 0.32);
        cursor: pointer;
        pointer-events: auto;
        transition: transform .25s ease, box-shadow .25s ease, filter .25s ease;
    }

    .offer-tab:hover {
        transform: translateX(4px);
        filter: brightness(1.05);
    }

    .offer-tab:focus-visible {
        outline: 2px solid #8b1a1a;
        outline-offset: 3px;
    }

    .offer-tab-pct {
        font-size: 1.5rem;
        font-weight: 900;
        line-height: 1;
        color: #b91c1c;
    }

    .offer-tab-label {
        writing-mode: vertical-rl;
        font-size: 0.82rem;
        font-weight: 800;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: #b91c1c;
    }

    .offer-tab-count {
        position: absolute;
        top: 6px;
        right: 6px;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        border-radius: 999px;
        background: #b91c1c;
        color: #fff;
        font-size: 0.66rem;
        font-weight: 800;
        line-height: 18px;
        text-align: center;
    }

    /* --- The popup panel --- */
    .offer-tab-panel {
        position: fixed;
        left: 58px;
        top: 50%;
        transform: translateY(-50%) translateX(-14px);
        width: 330px;
        max-width: calc(100vw - 78px);
        max-height: 72vh;
        display: flex;
        flex-direction: column;
        background: #fff;
        border-radius: 18px;
        border: 1px solid rgba(166, 124, 0, .22);
        box-shadow: 0 22px 60px rgba(15, 23, 42, .22);
        overflow: hidden;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .22s ease, transform .22s ease, visibility .22s ease;
    }

    .offer-tab-wrap.is-open .offer-tab-panel {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: translateY(-50%) translateX(0);
    }

    .offer-tab-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .22);
        z-index: -1;
        pointer-events: auto;
    }
</style>


<style>
    /* --- Panel internals --- */
    .offer-panel-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        padding: 18px 20px 16px;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 72%, #064e3b 100%);
    }

    .offer-panel-kicker {
        display: block;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #e7c96b;
    }

    .offer-panel-title {
        margin: 4px 0 0;
        font-size: 1.15rem;
        font-weight: 800;
        color: #fff;
    }

    .offer-panel-close {
        flex-shrink: 0;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, .25);
        background: rgba(255, 255, 255, .12);
        color: #fff;
        font-size: 1.3rem;
        line-height: 1;
        cursor: pointer;
        transition: background .2s ease;
    }

    .offer-panel-close:hover {
        background: rgba(255, 255, 255, .25);
    }

    .offer-panel-body {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: #f8fafc;
    }

    .offer-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border: 1.5px dashed #d4af37;
        border-radius: 14px;
        background: #fffdf5;
    }

    .offer-card.is-spent {
        border-color: #cbd5e1;
        background: #f1f5f9;
        opacity: .78;
    }

    .offer-card-main {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
        flex: 1;
    }

    .offer-card-headline {
        font-size: 0.92rem;
        font-weight: 800;
        color: #0f172a;
    }

    .offer-card-code {
        font-size: 0.85rem;
        font-weight: 800;
        letter-spacing: 1.5px;
        color: #a67c00;
        word-break: break-all;
    }

    .offer-card-meta {
        font-size: 0.72rem;
        color: #64748b;
    }

    .offer-card-reason {
        font-size: 0.72rem;
        font-weight: 600;
        color: #dc2626;
    }

    .offer-card-copy {
        flex-shrink: 0;
        padding: 7px 12px;
        border: none;
        border-radius: 999px;
        background: linear-gradient(135deg, #a67c00 0%, #d4af37 100%);
        color: #fff;
        font-size: 0.76rem;
        font-weight: 700;
        cursor: pointer;
        transition: filter .2s ease, transform .2s ease;
    }

    .offer-card-copy:hover:not(:disabled) {
        filter: brightness(1.08);
        transform: translateY(-1px);
    }

    .offer-card-copy:disabled {
        background: #e2e8f0;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .offer-panel-empty {
        margin: 0;
        padding: 18px 6px;
        text-align: center;
        font-size: 0.84rem;
        color: #64748b;
    }

    .offer-panel-foot {
        padding: 12px 16px;
        border-top: 1px solid #e2e8f0;
        background: #fff;
        text-align: center;
    }

    .offer-panel-cta {
        font-size: 0.82rem;
        font-weight: 700;
        color: #064e3b;
        text-decoration: none;
    }

    .offer-panel-cta:hover {
        color: #a67c00;
    }

    @media (max-width: 768px) {
        .offer-tab-wrap {
            width: 36px;
        }

        .offer-tab {
            width: 36px;
            padding: 12px 4px;
            border-radius: 0 20px 20px 0;
        }

        .offer-tab-label {
            font-size: 0.7rem;
            letter-spacing: 1.5px;
        }

        .offer-tab-panel {
            left: 46px;
            max-height: 64vh;
        }
    }
</style>
