<?php

use App\Services\MetalPriceService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

/** The provider's XAU/INR payload, trimmed to the fields we read. */
function goldApiResponse(float $gramPrice = 13221.3869, float $changePercent = 0.15): array
{
    return [
        'timestamp' => 1790409423,
        'datetime' => '2026-09-26T07:57:03Z',
        'metal' => 'XAU',
        'currency' => 'INR',
        'open_price' => 409894.9,
        'prev_close_price' => 410602.6,
        'low_price' => 407952.3,
        'high_price' => 412871.6,
        'price' => 411231.1,
        'change' => 628.5,
        'change_percent' => $changePercent,
        'price_per_unit' => ['gram' => $gramPrice, 'troy_ounce' => 411231.1],
        'melt_price_per_gram' => ['24k' => $gramPrice, '22k' => 12119.6047, '18k' => 9916.0402],
    ];
}

/**
 * Http::fake() *merges* stubs, so every helper below starts from a swapped-in
 * factory: otherwise an earlier stub keeps winning over a later outage.
 */
function fakeMetalRatesApi(): void
{
    Http::swap(new HttpFactory);

    Http::fake([
        '*/api/price/XAU/INR' => Http::response(goldApiResponse()),
        '*/api/price/XAG/INR' => Http::response([
            'metal' => 'XAG',
            'currency' => 'INR',
            'low_price' => 85000.0,
            'high_price' => 87000.0,
            'open_price' => 86000.0,
            'change' => -120.0,
            'change_percent' => -0.14,
            'price_per_unit' => ['gram' => 2760.0, 'troy_ounce' => 85840.0],
            'melt_price_per_gram' => ['24k' => 2760.0, '22k' => 2760.0],
        ]),
    ]);
}

/** The provider being unreachable or erroring. */
function fakeMetalRatesOutage(int $status = 500): void
{
    Http::swap(new HttpFactory);

    Http::fake(['*' => Http::response('upstream unavailable', $status)]);
}

beforeEach(function () {
    config([
        'metalprice.api_key' => 'test-key',
        'metalprice.cache_ttl' => 900,
        'metalprice.grams_per_unit' => 10,
    ]);

    Cache::forget(MetalPriceService::CACHE_KEY);
});

/* ───────────────────────────────────────────────────────────────
 |  The LIVE button and its gold/silver popup
   ─────────────────────────────────────────────────────────────── */

it('shows the live button in the storefront top bar', function () {
    fakeMetalRatesApi();

    get(route('home'))
        ->assertOk()
        ->assertSee('id="liveRatesBtn"', false)
        ->assertSee('>LIVE<', false)
        ->assertSee('id="liveRatesPanel"', false);
});

it('renders gold and silver rates in the popup at ten grams', function () {
    fakeMetalRatesApi();

    $response = get(route('home'))->assertOk();

    // Gold: 13221.3869 per gram -> 132,214 per 10 grams.
    $response->assertSee('132,214', false)
        ->assertSee('per 10 gram (1 tola)')
        // Silver: 2760.00 per gram -> 27,600 per 10 grams.
        ->assertSee('27,600', false)
        ->assertSee('Gold')
        ->assertSee('Silver')
        ->assertSee('+0.15%', false)
        ->assertSee('-0.14%', false);
});

it('converts ounce denominated high and low to the ten gram basis', function () {
    fakeMetalRatesApi();

    // 412871.6 and 407952.3 are per troy ounce; 31.1034768 grams is one ounce,
    // so both are restated on the same 10 gram basis as the headline price.
    get(route('home'))
        ->assertOk()
        ->assertSee('132,741', false)
        ->assertSee('131,160', false);
});

it('exposes the same rates through the refresh endpoint', function () {
    fakeMetalRatesApi();

    $response = get(route('metal-prices.index'))->assertOk();

    $response->assertJsonPath('available', true)
        ->assertJsonPath('unit_grams', 10)
        ->assertJsonPath('unit_label', 'per 10 gram (1 tola)')
        ->assertJsonPath('metals.gold.price', '₹132,214')
        ->assertJsonPath('metals.gold.direction', 'up')
        ->assertJsonPath('metals.gold.purity.22k.value', 121196.05)
        // Gold is sold by karat, silver by fineness.
        ->assertJsonPath('metals.gold.purity.24k.label', '24K')
        ->assertJsonPath('metals.silver.purity.24k.label', '999')
        ->assertJsonPath('metals.silver.price', '₹27,600')
        ->assertJsonPath('metals.silver.direction', 'down');

    // The flat map is what the browser swaps text from; its keys are dotted
    // (matching each element's data-live attribute) so they are not a path.
    $flat = $response->json('flat');

    expect($flat)->toHaveKeys(['gold.price', 'silver.price'])
        ->and($flat['gold.price'])->toBe('₹132,214')
        ->and($flat['silver.price'])->toBe('₹27,600');
});

it('sends the access token the provider requires', function () {
    fakeMetalRatesApi();

    get(route('metal-prices.index'))->assertOk();

    Http::assertSent(fn ($request) => $request->hasHeader('x-access-token', 'test-key')
        && str_contains($request->url(), '/api/price/XAU/INR'));
});

it('serves a single call per cache window', function () {
    fakeMetalRatesApi();

    get(route('metal-prices.index'))->assertOk();
    get(route('metal-prices.index'))->assertOk();
    get(route('home'))->assertOk();

    Http::assertSentCount(2); // one gold, one silver
});

it('keeps the live button and the promo in flow so they cannot overlap', function () {
    fakeMetalRatesApi();

    $html = get(route('home'))->assertOk()->getContent();

    $button = strpos($html, 'id="liveRatesBtn"');
    $promo = strpos($html, 'top-bar-promo');

    // Button first in the DOM, both in flow: the flex row places the button on
    // the left and the promo takes the rest. The button is deliberately not
    // absolutely positioned — that is what let them collide.
    expect($button)->toBeInt()->toBeLessThan($promo);

    // The promo must stay on one line. Wrapping is what made the bar twice as
    // tall and pushed the second line under the button.
    expect($html)->toContain('white-space: nowrap');
});

it('keeps the live rates stylesheet inside a single style block', function () {
    // Regression: a duplicated <style> plus a stray </style> ended the
    // stylesheet early, so every rule after it (.live-panel-title,
    // .live-panel-close, .live-panel-body) was painted as visible page text on
    // top of the popup — the panel looked broken and unstyled.
    $source = file_get_contents(resource_path('views/layouts/topbar.blade.php'));

    expect(substr_count($source, '<style>'))->toBe(1)
        ->and(substr_count($source, '</style>'))->toBe(1);

    $open = strpos($source, '<style>');
    $close = strpos($source, '</style>');

    $selectors = [
        'live-rates-btn',
        'live-rates-panel',
        'live-panel-head',
        'live-panel-title',
        'live-panel-close',
        'live-panel-body',
        'live-metal',
        'live-panel-foot',
    ];

    foreach ($selectors as $selector) {
        $at = strpos($source, '.top-bar .' . $selector);

        expect($at)->toBeInt('missing rule for .' . $selector)
            ->and($at)->toBeGreaterThan($open)
            ->and($at)->toBeLessThan($close);
    }
});

it('never leaks the popup styles into the rendered markup', function () {
    fakeMetalRatesApi();

    $html = get(route('home'))->assertOk()->getContent();

    // Anchor on the top bar's own stylesheet, then assert nothing after it is
    // raw CSS. While the block was duplicated, these selectors were painted as
    // visible text on top of the hero.
    $rule = strpos($html, '.top-bar .live-rates-btn');
    $styleEnd = strpos($html, '</style>', (int) $rule);

    expect($rule)->toBeInt()
        ->and($styleEnd)->toBeGreaterThan($rule)
        ->and(substr($html, $styleEnd + strlen('</style>')))->not->toContain('.top-bar .live-');
});

it('flags a negative day on the rate and turns the card edge red', function () {
    fakeMetalRatesApi();

    get(route('home'))
        ->assertOk()
        ->assertSee('is-down', false)
        ->assertSee('data-live-metal="silver"', false);
});

/* ───────────────────────────────────────────────────────────────
 |  Graceful degradation
   ─────────────────────────────────────────────────────────────── */

it('reports unavailability instead of breaking when the key is missing', function () {
    config(['metalprice.api_key' => '']);

    get(route('home'))
        ->assertOk()
        ->assertSee('is-offline', false)
        ->assertSee('Live rates are temporarily unavailable.', false);
});

it('keeps the last good rates when the provider starts failing', function () {
    fakeMetalRatesApi();

    get(route('metal-prices.index'))->assertOk();

    // TTL of 0 forces a refetch; the cache itself is what the fallback reads.
    config(['metalprice.cache_ttl' => 0]);
    fakeMetalRatesOutage();

    $response = get(route('metal-prices.index'))->assertOk();

    $response->assertJsonPath('stale', true)
        ->assertJsonPath('available', true)
        ->assertJsonPath('metals.gold.price', '₹132,214');
});

it('recovers as soon as the provider comes back', function () {
    fakeMetalRatesApi();

    get(route('metal-prices.index'))->assertOk();

    config(['metalprice.cache_ttl' => 0]);
    fakeMetalRatesOutage();
    get(route('metal-prices.index'))->assertJsonPath('stale', true);

    fakeMetalRatesApi(); // a working provider again

    get(route('metal-prices.index'))
        ->assertOk()
        ->assertJsonPath('stale', false)
        ->assertJsonPath('metals.gold.price', '₹132,214');
});

it('survives a provider response that is missing the price', function () {
    Http::fake(['*' => Http::response(['metal' => 'XAU'])]);

    get(route('home'))
        ->assertOk()
        ->assertSee('Live rates are temporarily unavailable.', false);
});
