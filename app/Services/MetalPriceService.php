<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetalPriceService
{
    public const CACHE_KEY = 'metal-prices:v1';

    /** Grams in one troy ounce, the unit the provider quotes high/low/open in. */
    private const GRAMS_PER_TROY_OUNCE = 31.1034768;

    /**
     * Gold leads because it leads the display order.
     *
     * "purity" maps the provider's melt keys to the label a jewellery shop
     * would actually print: gold is sold by karat, silver by fineness.
     *
     * @var array<string, array{symbol: string, name: string, purity: array<string, string>}>
     */
    public const METALS = [
        'gold' => [
            'symbol' => 'XAU',
            'name' => 'Gold',
            'purity' => ['24k' => '24K', '22k' => '22K', '18k' => '18K'],
        ],
        'silver' => [
            'symbol' => 'XAG',
            'name' => 'Silver',
            'purity' => ['24k' => '999'],
        ],
    ];

    /**
     * Current rates, cached. Never throws: a failed provider leaves the last
     * good numbers in place (flagged stale) or reports unavailability.
     *
     * @return array<string, mixed>
     */
    public function rates(bool $forceRefresh = false): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        // array_merge, not +: the cached payload already carries a "stale" key
        // and the union operator would never let these flags through.
        if (! $forceRefresh && $this->hasMetals($cached) && $this->isFresh($cached)) {
            return array_merge($cached, ['stale' => false]);
        }

        $metals = $this->fetchAll();

        if ($metals === []) {
            // The last good payload is the fallback, so it is kept for the stale
            // window and only the *freshness* check above decides when to refetch.
            if ($this->hasMetals($cached)) {
                return array_merge($cached, ['stale' => true]);
            }

            return [
                'metals' => [],
                'updated_at' => null,
                'stale' => false,
                'message' => 'Live rates are temporarily unavailable. Please check back shortly.',
            ];
        }

        $payload = [
            'metals' => $metals,
            'updated_at' => now()->toIso8601String(),
            'fetched_at' => now()->getTimestamp(),
            'stale' => false,
            'message' => null,
        ];

        Cache::put(self::CACHE_KEY, $payload, now()->addSeconds($this->staleTtl()));

        return $payload;
    }

    /**
     * The rates shaped for display.
     *
     * The top bar renders this and the refresh endpoint returns the exact same
     * structure, so a live update and a page load can never disagree on how a
     * rate is written.
     *
     * @return array<string, mixed>
     */
    public function displayPayload(): array
    {
        $rates = $this->rates();
        $unitGrams = $this->gramsPerUnit();
        $flat = [];
        $metals = [];

        foreach ($rates['metals'] as $key => $metal) {
            $direction = $this->direction((float) $metal['change_percent']);

            $flat["{$key}.price"] = $this->money($metal['price']);
            $flat["{$key}.direction"] = $direction;
            $flat["{$key}.change"] = $this->signedMoney($metal['change']);
            $flat["{$key}.change_percent"] = $this->signedNumber($metal['change_percent']).'%';
            $flat["{$key}.high"] = $this->money($metal['high']);
            $flat["{$key}.low"] = $this->money($metal['low']);
            $flat["{$key}.open"] = $this->money($metal['open']);

            $purity = [];

            foreach ($metal['purity'] as $purityKey => $row) {
                $flat["{$key}.purity.{$purityKey}"] = $this->money($row['value']);
                $purity[$purityKey] = $row + ['display' => $flat["{$key}.purity.{$purityKey}"]];
            }

            $metals[$key] = [
                'key' => $key,
                'name' => $metal['name'],
                'symbol' => $metal['symbol'],
                'price' => $flat["{$key}.price"],
                'change' => $flat["{$key}.change"],
                'change_percent' => $flat["{$key}.change_percent"],
                'high' => $flat["{$key}.high"],
                'low' => $flat["{$key}.low"],
                'open' => $flat["{$key}.open"],
                'direction' => $direction,
                'purity' => $purity,
            ];
        }

        $updatedAt = $rates['updated_at'] ? Carbon::parse($rates['updated_at']) : null;
        $flat['updated_at'] = $updatedAt ? $updatedAt->format('d M Y, h:i A') : '—';

        return [
            'available' => $metals !== [],
            'stale' => (bool) $rates['stale'],
            'message' => $metals === [] ? ($rates['message'] ?? 'Live rates are temporarily unavailable.') : null,
            'updated_at' => $flat['updated_at'],
            'updated_at_iso' => $rates['updated_at'],
            'unit_grams' => $unitGrams,
            'unit_label' => $this->unitLabel($unitGrams),
            'metals' => $metals,
            'flat' => $flat,
        ];
    }


    /**
     * The rates as plain numbers, for the public JSON API.
     *
     * displayPayload() hands back pre-formatted strings because the top bar
     * writes them straight into the DOM. A React client has to do its own maths
     * with these, so it gets the raw figure next to a ready-to-render string
     * and never has to parse "₹1,32,214" back into a number.
     *
     * The two shapes are deliberately separate: a formatting tweak for the top
     * bar can never change what the API reports.
     *
     * @return array<string, mixed>
     */
    public function rawPayload(): array
    {
        $rates = $this->rates();
        $unitGrams = $this->gramsPerUnit();
        $metals = [];

        // A list, not a keyed map: React can map() straight over it and keep
        // using `key` for lookups.
        foreach ($rates['metals'] as $key => $metal) {
            $changePercent = (float) $metal['change_percent'];
            $direction = $this->direction($changePercent);

            $purity = [];

            foreach ($metal['purity'] as $purityKey => $row) {
                $purity[] = [
                    'key' => $purityKey,
                    'label' => $row['label'],
                    'price' => (float) $row['value'],
                    'price_display' => $this->money((float) $row['value']),
                ];
            }

            $metals[] = [
                'key' => $key,
                'name' => $metal['name'],
                'symbol' => $metal['symbol'],
                'price' => (float) $metal['price'],
                'price_display' => $this->money((float) $metal['price']),
                'change' => (float) $metal['change'],
                'change_display' => $this->signedMoney((float) $metal['change']),
                'change_percent' => $changePercent,
                'change_percent_display' => $this->signedNumber($changePercent).'%',
                'high' => (float) $metal['high'],
                'low' => (float) $metal['low'],
                'open' => (float) $metal['open'],
                'direction' => $direction,
                'is_up' => $direction === 'up',
                'purity' => $purity,
            ];
        }

        $updatedAt = $rates['updated_at'] ? Carbon::parse($rates['updated_at']) : null;

        return [
            'available' => $metals !== [],
            'stale' => (bool) $rates['stale'],
            'message' => $metals === [] ? ($rates['message'] ?? 'Live rates are temporarily unavailable.') : null,
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'unit_grams' => $unitGrams,
            'unit_label' => $this->unitLabel($unitGrams),
            'updated_at' => $rates['updated_at'],
            'updated_at_display' => $updatedAt ? $updatedAt->format('d M Y, h:i A') : null,
            'metals' => $metals,
        ];
    }

    /**
     * Fetch every configured metal, skipping the ones that fail so a single
     * bad response cannot take the whole top bar down.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchAll(): array
    {
        $apiKey = trim((string) config('metalprice.api_key'));

        if ($apiKey === '') {
            Log::warning('Metal rates skipped: METAL_PRICE_API_KEY is not set.');

            return [];
        }

        $metals = [];

        foreach (self::METALS as $key => $definition) {
            $metal = $this->fetchMetal($apiKey, $key, $definition);

            if ($metal !== null) {
                $metals[$key] = $metal;
            }
        }

        return $metals;
    }

    /**
     * @param  array{symbol: string, name: string, purity: array<string, string>}  $definition
     * @return array<string, mixed>|null
     */
    private function fetchMetal(string $apiKey, string $key, array $definition): ?array
    {
        $symbol = $definition['symbol'];
        $url = rtrim((string) config('metalprice.base_url'), '/')."/price/{$symbol}/INR";

        try {
            $response = Http::withHeaders(['x-access-token' => $apiKey])
                ->acceptJson()
                ->timeout((int) config('metalprice.timeout'))
                ->connectTimeout((int) config('metalprice.connect_timeout'))
                ->get($url);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! $response->successful()) {
            Log::warning("Metal rates: {$symbol} request returned HTTP {$response->status()}.");

            return null;
        }

        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        return $this->normalise($key, $definition, $body);
    }

    /**
     * Turn one provider payload into our own shape, with every ounce-denominated
     * figure converted to the same per-unit basis as the headline price.
     *
     * @param  array{symbol: string, name: string, purity: array<string, string>}  $definition
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function normalise(string $key, array $definition, array $body): ?array
    {
        $gramsPerUnit = $this->gramsPerUnit();

        $pricePerGram = (float) Arr::get($body, 'price_per_unit.gram', 0);

        if ($pricePerGram <= 0) {
            return null;
        }

        $ounceToUnit = $gramsPerUnit / self::GRAMS_PER_TROY_OUNCE;

        $purity = [];
        $meltPerGram = (array) Arr::get($body, 'melt_price_per_gram', []);

        foreach ($definition['purity'] as $meltKey => $label) {
            if (! isset($meltPerGram[$meltKey])) {
                continue;
            }

            $purity[$meltKey] = [
                'label' => $label,
                'value' => round(((float) $meltPerGram[$meltKey]) * $gramsPerUnit, 2),
            ];
        }

        return [
            'key' => $key,
            'name' => $definition['name'],
            'symbol' => $definition['symbol'],
            'price' => round($pricePerGram * $gramsPerUnit, 2),
            'change' => round(((float) Arr::get($body, 'change', 0)) * $ounceToUnit, 2),
            'change_percent' => round((float) Arr::get($body, 'change_percent', 0), 2),
            'high' => round(((float) Arr::get($body, 'high_price', 0)) * $ounceToUnit, 2),
            'low' => round(((float) Arr::get($body, 'low_price', 0)) * $ounceToUnit, 2),
            'open' => round(((float) Arr::get($body, 'open_price', 0)) * $ounceToUnit, 2),
            'purity' => $purity,
        ];
    }

    private function gramsPerUnit(): int
    {
        return max(1, (int) config('metalprice.grams_per_unit'));
    }

    private function unitLabel(int $grams): string
    {
        return $grams === 10 ? 'per 10 gram (1 tola)' : "per {$grams} gram";
    }

    private function direction(float $changePercent): string
    {
        return match (true) {
            $changePercent > 0 => 'up',
            $changePercent < 0 => 'down',
            default => 'flat',
        };
    }

    private function money(float $amount): string
    {
        return '₹'.number_format($amount, 0);
    }

    private function signedMoney(float $amount): string
    {
        return ($amount > 0 ? '+' : ($amount < 0 ? '-' : '')).'₹'.number_format(abs($amount), 0);
    }

    private function signedNumber(float $amount, int $decimals = 2): string
    {
        return ($amount > 0 ? '+' : ($amount < 0 ? '-' : '')).number_format(abs($amount), $decimals);
    }

    private function staleTtl(): int
    {
        return max(60, (int) config('metalprice.stale_ttl'));
    }

    private function cacheTtl(): int
    {
        return max(0, (int) config('metalprice.cache_ttl'));
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function isFresh(array $cached): bool
    {
        if (! isset($cached['fetched_at'])) {
            return false;
        }

        // Strictly greater: a zero TTL means "always refetch", so a payload
        // cached in this very second must not read as fresh.
        return ((int) $cached['fetched_at']) + $this->cacheTtl() > now()->getTimestamp();
    }

    /**
     * @param  mixed  $cached
     */
    private function hasMetals($cached): bool
    {
        return is_array($cached) && ! empty($cached['metals']) && is_array($cached['metals']);
    }
}
