@php
    // Live gold & silver rates, served from MetalPriceService via the
    // "layouts.topbar" view composer. The default keeps the bar rendering even
    // if the composer is ever bypassed.
    $liveRates = $liveRates ?? ['available' => false, 'metals' => [], 'flat' => [], 'message' => null, 'stale' => false, 'unit_label' => ''];
@endphp

<div class="top-bar" id="topBar">
    <div class="top-bar-inner">

        {{-- ===== LIVE METAL RATES ===== --}}
        <div class="live-rates" id="liveRates" data-live-rates-url="{{ route('metal-prices.index') }}">
            <button type="button" class="live-rates-btn{{ $liveRates['available'] ? '' : ' is-offline' }}"
                id="liveRatesBtn" aria-expanded="false" aria-controls="liveRatesPanel"
                aria-label="Live gold and silver rates">
                <span class="live-pulse" aria-hidden="true"></span>
                <span class="live-label">LIVE</span>
                <i class="bi bi-chevron-down live-caret" aria-hidden="true"></i>
            </button>

            <div class="live-rates-panel" id="liveRatesPanel" role="dialog" aria-modal="false"
                aria-label="Live gold and silver rates" aria-hidden="true">
                <div class="live-panel-head">
                    <div>
                        <span class="live-panel-kicker">Spot Rates</span>
                        <h2 class="live-panel-title">Gold &amp; Silver</h2>
                    </div>
                    <button type="button" class="live-panel-close" id="liveRatesClose" aria-label="Close live rates">
                        &times;
                    </button>
                </div>

                <div class="live-panel-body">
                    @forelse($liveRates['metals'] as $metal)
                        <article class="live-metal" data-live-metal="{{ $metal['key'] }}"
                            data-live-direction="{{ $metal['direction'] }}">
                            <div class="live-metal-top">
                                <span class="live-metal-name">
                                    <i class="bi bi-{{ $metal['key'] === 'gold' ? 'gem' : 'circle-half' }}" aria-hidden="true"></i>
                                    {{ $metal['name'] }}
                                </span>
                                <span class="live-metal-change is-{{ $metal['direction'] }}"
                                    data-live-dir="{{ $metal['key'] }}.direction">
                                    @if($metal['direction'] === 'up')
                                        <i class="bi bi-caret-up-fill" aria-hidden="true"></i>
                                    @elseif($metal['direction'] === 'down')
                                        <i class="bi bi-caret-down-fill" aria-hidden="true"></i>
                                    @else
                                        <i class="bi bi-dash" aria-hidden="true"></i>
                                    @endif
                                    {{ $metal['change_percent'] }}
                                </span>
                            </div>

                            <div class="live-metal-price" data-live="{{ $metal['key'] }}.price">{{ $metal['price'] }}</div>
                            <div class="live-metal-unit">{{ $liveRates['unit_label'] }}</div>

                            <dl class="live-metal-stats">
                                <div>
                                    <dt>Day Change</dt>
                                    <dd data-live="{{ $metal['key'] }}.change">{{ $metal['change'] }}</dd>
                                </div>
                                <div>
                                    <dt>High</dt>
                                    <dd data-live="{{ $metal['key'] }}.high">{{ $metal['high'] }}</dd>
                                </div>
                                <div>
                                    <dt>Low</dt>
                                    <dd data-live="{{ $metal['key'] }}.low">{{ $metal['low'] }}</dd>
                                </div>
                            </dl>

                            @if($metal['purity'])
                                <div class="live-metal-purity">
                                    @foreach($metal['purity'] as $purityKey => $purity)
                                        <span class="live-purity-chip">
                                            <em>{{ $purity['label'] }}</em>
                                            <span data-live="{{ $metal['key'] }}.purity.{{ $purityKey }}">{{ $purity['display'] }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @empty
                        <p class="live-panel-empty">{{ $liveRates['message'] ?? 'Live rates are temporarily unavailable.' }}</p>
                    @endforelse
                </div>

                <div class="live-panel-foot">
                    <span class="live-panel-updated">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        Updated <span data-live="updated_at">{{ $liveRates['updated_at'] ?? '—' }}</span>
                    </span>
                    <button type="button" class="live-panel-refresh" id="liveRatesRefresh" aria-label="Refresh live rates">
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>

        <span class="top-bar-promo">FLAT 15% Off on select Silver Jewellery</span>
    </div>

    {{-- Rates are indicative spot prices, refreshed periodically. --}}
    <p class="visually-hidden">Gold and silver spot prices are indicative and may differ from the final billed price.</p>

<style>
    /* ===== LIVE METAL RATES (top bar) =====
       Every rule is scoped under .top-bar so it outranks the broad
       ".top-bar span { color: white; display: inline-flex }" base rule. */

    /* Everything stays in flow: the button is the first flex item and the promo
       takes the rest, so the two can never overlap and the bar can't grow a
       second line. flex-grow is what pins the inner to the full bar width. */
    .top-bar { position: relative; }

    .top-bar .top-bar-inner {
        display: flex;
        align-items: center;
        gap: 14px;
        flex: 1 1 auto;
        min-width: 0;
    }

    .top-bar .live-rates {
        position: relative;
        flex: 0 0 auto;
    }

    .top-bar .top-bar-promo {
        flex: 1 1 auto;
        min-width: 0;
        text-align: center;
        /* One line, always: a wrapped promo would make the bar twice as tall. */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* --- The LIVE button --- */
    .top-bar .live-rates-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 3px 12px 3px 10px;
        border: 1px solid rgba(231, 201, 107, .45);
        border-radius: 999px;
        background: rgba(231, 201, 107, .10);
        color: #e7c96b;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 1.4px;
        line-height: 1.6;
        cursor: pointer;
        transition: background .2s ease, color .2s ease, border-color .2s ease;
    }

    .top-bar .live-rates-btn:hover,
    .top-bar .live-rates-btn[aria-expanded="true"] {
        background: #e7c96b;
        border-color: #e7c96b;
        color: #0f172a;
    }

    .top-bar .live-rates-btn:focus-visible {
        outline: 2px solid #e7c96b;
        outline-offset: 2px;
    }

    .top-bar .live-rates-btn.is-offline {
        border-color: rgba(148, 163, 184, .45);
        background: rgba(148, 163, 184, .10);
        color: #94a3b8;
    }

    .top-bar .live-rates-btn .live-label {
        display: inline;
        color: inherit;
    }

    .top-bar .live-pulse {
        width: 7px;
        height: 7px;
        flex-shrink: 0;
        border-radius: 50%;
        background: #4ade80;
        animation: topbarLivePulse 1.8s ease-out infinite;
    }

    .top-bar .live-rates-btn.is-offline .live-pulse {
        background: #94a3b8;
        animation: none;
    }

    .top-bar .live-caret {
        font-size: 0.62rem;
        color: inherit;
    }

    @keyframes topbarLivePulse {
        0% { box-shadow: 0 0 0 0 rgba(74, 222, 128, .65); }
        70% { box-shadow: 0 0 0 7px rgba(74, 222, 128, 0); }
        100% { box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); }
    }

    @media (prefers-reduced-motion: reduce) {
        .top-bar .live-pulse { animation: none; }
    }

    /* --- The popup panel --- */
    .top-bar .live-rates-panel {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        /* Above the fixed offer tab (1040), back-to-top (10000) and the offer
           modal (9999): a transient popup should never be clipped by chrome. */
        z-index: 10060;
        /* 340px squeezed the 3 stat columns until the values wrapped. */
        width: 380px;
        max-width: calc(100vw - 24px);
        box-sizing: border-box;
        background: #fff;
        border-radius: 16px;
        border: 1px solid rgba(166, 124, 0, .22);
        box-shadow: 0 22px 60px rgba(15, 23, 42, .28);
        overflow: hidden;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: translateY(-8px);
        transition: opacity .2s ease, transform .2s ease, visibility .2s ease;
    }

    .top-bar .live-rates.is-open .live-rates-panel {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: translateY(0);
    }

    .top-bar .live-panel-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        padding: 14px 18px 13px;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 72%, #064e3b 100%);
    }

    .top-bar .live-panel-kicker {
        display: block;
        font-size: 0.62rem;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #e7c96b;
    }

    .top-bar .live-panel-title {
        margin: 3px 0 0;
        font-size: 1.05rem;
        font-weight: 800;
        line-height: 1.2;
        color: #fff;
    }

    /* Centred box button: a bare "&times;" glyph in a flex row collapses to a
       sliver unless the box itself is laid out. */
    .top-bar .live-panel-close {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        padding: 0;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, .28);
        background: rgba(255, 255, 255, .12);
        color: #fff;
        font-size: 1.15rem;
        line-height: 1;
        cursor: pointer;
        transition: background .2s ease;
    }

    .top-bar .live-panel-close:hover {
        background: rgba(255, 255, 255, .28);
    }

    .top-bar .live-panel-body {
        /* Scrolls internally, and can never grow past the viewport, so the
           footer stays reachable on short screens. */
        max-height: min(58vh, calc(100vh - 190px));
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #f8fafc;
    }

    /* --- Metal cards --- */
    .top-bar .live-metal {
        padding: 13px 15px;
        border-radius: 13px;
        background: #fff;
        border: 1px solid #e2e8f0;
    }

    .top-bar .live-metal[data-live-direction="up"] { border-left: 3px solid #16a34a; }
    .top-bar .live-metal[data-live-direction="down"] { border-left: 3px solid #dc2626; }
    .top-bar .live-metal[data-live-direction="flat"] { border-left: 3px solid #94a3b8; }

    .top-bar .live-metal-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .top-bar .live-metal-name {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        font-weight: 800;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #334155;
    }

    .top-bar .live-metal-name i {
        color: #a67c00;
        font-size: 0.95rem;
    }

    .top-bar .live-metal-change {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
    }

    .top-bar .live-metal-change.is-up { background: rgba(22, 163, 74, .12); color: #15803d; }
    .top-bar .live-metal-change.is-down { background: rgba(220, 38, 38, .12); color: #b91c1c; }
    .top-bar .live-metal-change.is-flat { background: rgba(100, 116, 139, .14); color: #475569; }

    .top-bar .live-metal-price {
        margin-top: 6px;
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.15;
        color: #0f172a;
    }

    .top-bar .live-metal-unit {
        font-size: 0.68rem;
        color: #64748b;
    }

    .top-bar .live-metal-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
        margin: 11px 0 0;
    }

    .top-bar .live-metal-stats dt {
        font-size: 0.62rem;
        font-weight: 700;
        letter-spacing: .6px;
        text-transform: uppercase;
        color: #94a3b8;
    }

    .top-bar .live-metal-stats dd {
        margin: 1px 0 0;
        font-size: 0.8rem;
        font-weight: 700;
        color: #334155;
    }

    .top-bar .live-metal-purity {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed #e2e8f0;
    }

    .top-bar .live-purity-chip {
        display: inline-flex;
        align-items: baseline;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 999px;
        background: #fdf6e3;
        border: 1px solid rgba(166, 124, 0, .25);
        font-size: 0.74rem;
        font-weight: 700;
        color: #7c5c00;
    }

    .top-bar .live-purity-chip em {
        font-style: normal;
        font-size: 0.62rem;
        font-weight: 800;
        letter-spacing: .5px;
        color: #a67c00;
    }

    .top-bar .live-panel-empty {
        margin: 0;
        padding: 22px 10px;
        text-align: center;
        font-size: 0.82rem;
        color: #64748b;
    }

    /* --- Footer --- */
    .top-bar .live-panel-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 9px 16px;
        border-top: 1px solid #e2e8f0;
        background: #fff;
    }

    .top-bar .live-panel-updated {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.7rem;
        color: #64748b;
    }

    .top-bar .live-panel-refresh {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        cursor: pointer;
        transition: color .2s ease, border-color .2s ease, transform .3s ease;
    }

    .top-bar .live-panel-refresh:hover {
        color: #a67c00;
        border-color: #d4af37;
    }

    .top-bar .live-panel-refresh.is-loading i {
        display: inline-block;
        animation: topbarLiveSpin .8s linear infinite;
    }

    @keyframes topbarLiveSpin {
        to { transform: rotate(360deg); }
    }

    @media (max-width: 767px) {
        /* Tighten the pill so the promo keeps as much room as possible; it
           truncates with an ellipsis rather than wrapping. */
        .top-bar .live-rates-btn {
            padding: 2px 9px 2px 8px;
            letter-spacing: 1px;
        }

        .top-bar .top-bar-inner {
            gap: 8px;
        }

        /* The panel is anchored to the button's left edge, which is what keeps
           it on screen — centring it on a button that hugs the left edge would
           push half the panel off the viewport. */
        .top-bar .live-rates-panel {
            width: min(300px, calc(100vw - 24px));
        }
    }
</style>

</div>
