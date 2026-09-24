<?php

namespace App\Services;

use App\Models\Combo;
use Illuminate\Support\Collection;

/**
 * Works out the combo price a customer actually gets when every product of an
 * active combo is sitting in their cart as a normal (non-bundle) cart line.
 *
 * The maths always uses the prices stored on the cart lines (never the
 * catalogue price) so the cart page, the checkout page and the generated order
 * all reconcile to exactly the same amount.
 */
class ComboService
{
    /**
     * Build the combo summary for a cart.
     *
     * The returned array looks like:
     * [
     *     'discount' => 738.77,                       // total combo saving
     *     'combos' => [
     *         [
     *             'combo'           => Combo,
     *             'line_ids'        => ['12', '13', '14'],
     *             'product_count'   => 3,
     *             'sets'            => 1,
     *             'regular_total'   => 8288.00,       // cart prices before combo
     *             'combo_price'     => 7549.23,       // what the customer pays
     *             'discount'        => 738.77,
     *             'savings_percent' => 8.9,
     *         ],
     *     ],
     *     'line_allocations' => [
     *         '12' => [
     *             'regular_total' => 3000.00,
     *             'combo_total'   => 2732.50,
     *             'discount'      => 267.50,
     *             'combo_units'   => 1,
     *             'combo_names'   => ['Combo name'],
     *         ],
     *     ],
     * ]
     *
     * @param  \Illuminate\Support\Collection<int, mixed>|array<int, mixed>  $cartItems
     * @return array{discount: float, combos: array<int, array<string, mixed>>, line_allocations: array<string, array<string, mixed>>}
     */
    public function summarize($cartItems): array
    {
        $lines = $this->normalizeLines($cartItems);

        $summary = [
            'discount' => 0.0,
            'combos' => [],
            'line_allocations' => [],
        ];

        if ($lines->isEmpty()) {
            return $summary;
        }

        $productIds = $lines->pluck('product_id')->unique()->values()->all();

        $combos = Combo::query()
            ->whereHas('products', fn ($query) => $query->whereIn('products.id', $productIds))
            ->active()
            ->with('products:id,name')
            ->get();

        // Cart line id => how many units are already taken by an earlier combo.
        $claimedUnits = [];

        foreach ($combos as $combo) {
            $comboProductIds = $combo->products
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            // A combo needs at least two different products to be an offer.
            if ($comboProductIds->count() < 2) {
                continue;
            }

            // How many times the whole combo can be paid for out of this cart.
            $sets = null;

            foreach ($comboProductIds as $productId) {
                $available = 0;

                foreach ($lines->where('product_id', $productId) as $line) {
                    $available += max(0, (int) $line['quantity'] - ($claimedUnits[$line['id']] ?? 0));
                }

                $sets = is_null($sets) ? $available : min($sets, $available);
            }

            if (empty($sets) || $sets < 1) {
                continue;
            }

            $units = $this->takeUnits($lines, $comboProductIds, (int) $sets, $claimedUnits);
            $regularTotal = round((float) collect($units)->sum('unit_price'), 2);

            if ($regularTotal <= 0) {
                continue;
            }

            $discount = $this->comboDiscount($combo, $regularTotal, (int) $sets);
            $comboPrice = round($regularTotal - $discount, 2);
            $allocatedUnitPrices = $this->allocate($units, $comboPrice);

            foreach ($units as $index => $unit) {
                $lineId = (string) $unit['line_id'];
                $claimedUnits[$lineId] = ($claimedUnits[$lineId] ?? 0) + 1;

                $allocation = $summary['line_allocations'][$lineId] ?? [
                    'regular_total' => 0.0,
                    'combo_total' => 0.0,
                    'discount' => 0.0,
                    'combo_units' => 0,
                    'combo_names' => [],
                ];

                $allocation['regular_total'] = round($allocation['regular_total'] + (float) $unit['unit_price'], 2);
                $allocation['combo_total'] = round($allocation['combo_total'] + (float) $allocatedUnitPrices[$index], 2);
                $allocation['discount'] = round($allocation['regular_total'] - $allocation['combo_total'], 2);
                $allocation['combo_units']++;

                if (! in_array($combo->name, $allocation['combo_names'], true)) {
                    $allocation['combo_names'][] = (string) $combo->name;
                }

                $summary['line_allocations'][$lineId] = $allocation;
            }

            $summary['discount'] = round($summary['discount'] + $discount, 2);
            $summary['combos'][] = [
                'combo' => $combo,
                'line_ids' => collect($units)->pluck('line_id')->unique()->values()->all(),
                'product_count' => $comboProductIds->count(),
                'sets' => (int) $sets,
                'regular_total' => $regularTotal,
                'combo_price' => $comboPrice,
                'discount' => $discount,
                'savings_percent' => round($discount / $regularTotal * 100, 1),
            ];
        }

        return $summary;
    }

    /** Total combo saving for a cart. */
    public function discount($cartItems): float
    {
        return (float) $this->summarize($cartItems)['discount'];
    }

    /**
     * Whether the current cart/order contains an actual applied combo.
     *
     * Complete combos detected from ordinary product lines are present in the
     * summary's `combos` array. Explicit combo offers are stored as cart lines
     * with combo_id and already carry their allocated combo price, so they must
     * also be detected directly.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>|array<int, mixed>  $cartItems
     * @param  array<string, mixed>|null  $summary
     */
    public function hasAppliedCombo($cartItems, ?array $summary = null): bool
    {
        $explicitItems = collect($cartItems)->filter(
            fn ($item) => ! empty($item->combo_id ?? null)
        );

        if ($explicitItems->isNotEmpty()) {
            $comboIds = $explicitItems
                ->map(fn ($item) => (int) $item->combo_id)
                ->unique()
                ->values();

            $requiredProducts = Combo::query()
                ->whereIn('id', $comboIds)
                ->with('products:id')
                ->get()
                ->mapWithKeys(fn (Combo $combo) => [
                    $combo->id => $combo->products->pluck('id')->map(fn ($id) => (int) $id),
                ]);

            $hasCompleteExplicitCombo = $explicitItems
                ->groupBy(fn ($item) => (int) $item->combo_id)
                ->contains(function ($items, int $comboId) use ($requiredProducts): bool {
                    $required = $requiredProducts->get($comboId, collect());
                    $present = $items
                        ->pluck('product_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values();

                    return $required->count() >= 2 && $required->diff($present)->isEmpty();
                });

            if ($hasCompleteExplicitCombo) {
                return true;
            }
        }

        $summary ??= $this->summarize($cartItems);

        return ! empty($summary['combos']);
    }

    /** Regular (pre-combo) value of a single cart line. */
    public function lineTotal($cartItem): float
    {
        return round((int) ($cartItem->quantity ?? 0) * (float) ($cartItem->price ?? 0), 2);
    }

    /** What the customer pays for a cart line once combo prices are applied. */
    public function chargedLineTotal($cartItem, array $summary): float
    {
        $gross = $this->lineTotal($cartItem);
        $allocation = $summary['line_allocations'][(string) ($cartItem->id ?? '')] ?? null;

        if (! $allocation) {
            return $gross;
        }

        return round(max(0, $gross - (float) $allocation['discount']), 2);
    }

    /**
     * Normalise the caller's cart items (Eloquent Cart models or the guest cart
     * stdClass objects) into plain arrays the combo maths can run on.
     *
     * Bundle lines already carry their own discounted price, so they never take
     * part in another combo.
     */
    private function normalizeLines($cartItems): Collection
    {
        return collect($cartItems)
            ->filter(fn ($item) => empty($item->combo_id))
            ->map(fn ($item) => [
                'id' => (string) ($item->id ?? ''),
                'product_id' => (int) ($item->product_id ?? optional($item->product)->id ?? 0),
                'quantity' => max(0, (int) ($item->quantity ?? 0)),
                'unit_price' => (float) ($item->price ?? 0),
            ])
            ->filter(fn ($line) => $line['id'] !== '' && $line['product_id'] > 0 && $line['quantity'] > 0)
            ->values();
    }

    /**
     * Reserve the units needed by $sets complete combo sets, taking units from
     * the cart lines in order and skipping units another combo already claimed.
     *
     * @return array<int, array{line_id: string, product_id: int, unit_price: float}>
     */
    private function takeUnits(Collection $lines, Collection $comboProductIds, int $sets, array $claimedUnits): array
    {
        $units = [];

        foreach ($comboProductIds as $productId) {
            $needed = $sets;

            foreach ($lines->where('product_id', $productId) as $line) {
                if ($needed <= 0) {
                    break;
                }

                $available = max(0, (int) $line['quantity'] - ($claimedUnits[$line['id']] ?? 0));
                $use = min($available, $needed);

                for ($i = 0; $i < $use; $i++) {
                    $units[] = [
                        'line_id' => $line['id'],
                        'product_id' => (int) $productId,
                        'unit_price' => (float) $line['unit_price'],
                    ];
                }

                $needed -= $use;
            }
        }

        return $units;
    }

    /** Combo saving for the reserved units (never more than they are worth). */
    private function comboDiscount(Combo $combo, float $regularTotal, int $sets): float
    {
        $discount = $combo->discount_type === 'percent'
            ? $regularTotal * ((float) $combo->discount_value / 100)
            : (float) $combo->discount_value * max(1, $sets);

        return round(min(max($discount, 0.0), $regularTotal), 2);
    }

    /**
     * Spread the combo price across the reserved units proportionally and fix
     * the last paise so the allocated prices add up to the combo price exactly.
     *
     * @param  array<int, array{line_id: string, product_id: int, unit_price: float}>  $units
     * @return array<int, float>
     */
    private function allocate(array $units, float $comboPrice): array
    {
        $count = count($units);

        if ($count === 0) {
            return [];
        }

        $regularTotal = (float) collect($units)->sum('unit_price');

        if ($regularTotal <= 0) {
            $prices = array_fill(0, $count, round($comboPrice / $count, 2));
        } else {
            $factor = $comboPrice / $regularTotal;
            $prices = array_map(
                fn ($unit) => round((float) $unit['unit_price'] * $factor, 2),
                $units
            );
        }

        $difference = round($comboPrice - array_sum($prices), 2);
        $index = 0;
        $guard = 0;

        while (abs($difference) >= 0.01 && $guard < ($count * 500)) {
            $target = $index % $count;
            $step = $difference > 0 ? 0.01 : -0.01;

            if ($prices[$target] + $step >= 0) {
                $prices[$target] = round($prices[$target] + $step, 2);
                $difference = round($difference - $step, 2);
            }

            $index++;
            $guard++;
        }

        return $prices;
    }
}
