<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Combo;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\ComboService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in customer's cart.
 *
 *   GET    /gehna/api/v1/cart                 list lines and totals
 *   POST   /gehna/api/v1/cart                 add a product or variation
 *   POST   /gehna/api/v1/cart/combo            add a bundle offer
 *   PATCH  /gehna/api/v1/cart/{id}            change quantity
 *   DELETE /gehna/api/v1/cart/{id}            remove one line
 *   DELETE /gehna/api/v1/cart                 empty the cart
 *
 * Lines are always scoped to $request->user()->id. A cart id from another
 * account therefore 404s instead of being readable or writable, and no
 * endpoint trusts a price from the client: the unit price is resolved from
 * the product and variation rows, so a tampered request cannot set its own
 * price.
 *
 * Bundle pricing is delegated to ComboService, the same service the Blade cart
 * and checkout use. It used not to be consulted here at all, which meant a
 * combo added through this API was charged at full product price while the
 * website discounted it - the customer-facing symptom was a cart whose
 * subtotal ignored the offer entirely.
 */
class CartController extends ApiController
{
    public function __construct(private readonly ComboService $comboService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respond($request);
    }

    /**
     * Add a line, or top up the quantity if the same product/variation is
     * already in the cart.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variation_id' => ['nullable', 'integer', 'exists:product_variations,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::where('is_active', true)->findOrFail($data['product_id']);
        $quantity = (int) ($data['quantity'] ?? 1);
        $variation = null;

        if (! empty($data['product_variation_id'])) {
            // Scoped to this product so a client cannot pair a product with
            // another product's variation and break the cart maths.
            $variation = ProductVariation::where('is_active', true)
                ->where('product_id', $product->id)
                ->findOrFail($data['product_variation_id']);
        }

        if ($this->isSoldOut($product, $variation)) {
            throw ValidationException::withMessages([
                'quantity' => 'This item is out of stock.',
            ]);
        }

        // Price always comes from the database, never from the request body.
        $price = $variation ? $variation->effectivePrice() : $product->effectivePrice();

        $line = Cart::firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
            'product_variation_id' => $variation?->id,
            'combo_id' => null,
        ]);

        $line->quantity = (int) $line->quantity + $quantity;
        $line->price = $price;
        $line->save();

        return $this->respond($request, 'Added to cart', Response::HTTP_CREATED);
    }

    /**
     * Add a bundle offer.
     *
     * Each product in the combo becomes its own cart line linked by combo_id and
     * priced at its allocated share of the bundle price, so the lines add up to
     * exactly the discounted total. Sending the same products through the plain
     * cart endpoint one by one would charge full price for every one of them,
     * which is what this action exists to prevent.
     *
     * No price is taken from the request body: the bundle price is recomputed
     * from the products' own current prices, so a client cannot post a stale or
     * tampered figure and neither can an admin who edited a product after the
     * bundle was created.
     */
    public function storeCombo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'combo_id' => ['required', 'integer', 'exists:combos,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $combo = Combo::where('id', $data['combo_id'])->with('sellableProducts')->first();

        // isLive() covers a switched-off combo and one whose schedule window has
        // closed, so an expired bundle can never be added at its old price.
        if (! $combo || ! $combo->isLive()) {
            throw ValidationException::withMessages([
                'combo_id' => 'This combo is no longer available.',
            ]);
        }

        $products = $combo->sellableProducts;

        // A product deactivated after the bundle was built must not keep being
        // sold inside it, and a combo that drops below two sellable products is
        // no longer a bundle at all.
        if ($products->count() < 2) {
            throw ValidationException::withMessages([
                'combo_id' => 'This combo no longer has enough available products to be offered.',
            ]);
        }

        $quantity = (int) ($data['quantity'] ?? 1);
        $allocated = $this->comboService->allocateComboUnitPrices($combo, $products);

        DB::transaction(function () use ($request, $combo, $products, $allocated, $quantity) {
            foreach ($products as $product) {
                $line = Cart::firstOrNew([
                    'user_id' => $request->user()->id,
                    'product_id' => $product->id,
                    'product_variation_id' => null,
                    'combo_id' => $combo->id,
                ]);

                $line->quantity = (int) $line->quantity + $quantity;
                // Re-applied on every add, not just on create: this line may
                // still be holding a restored regular price after the customer
                // removed a sibling product, and bumping the quantity alone
                // would leave the discount permanently switched off.
                $line->price = $allocated[$product->id] ?? (float) $product->effectivePrice();
                $line->save();
            }
        });

        return $this->respond($request, 'Combo added to cart', Response::HTTP_CREATED);
    }

    /** Set (not increment) the quantity of one line. */
    public function update(Request $request, int $cart): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $line = $this->ownedLine($request, $cart);
        $quantity = (int) $data['quantity'];

        // quantity 0 is the API's way of saying "remove this line", so the
        // React cart drawer can drop a row without a second round trip.
        if ($quantity === 0) {
            $line->delete();

            return $this->respond($request, 'Item removed');
        }

        $line->quantity = $quantity;
        $line->save();

        return $this->respond($request, 'Cart updated');
    }


    /**
     * Build the standard cart response.
     *
     * Every cart action funnels through here so they cannot drift apart on
     * pricing. Two steps matter:
     *
     * 1. applyExplicitComboPrices() puts the regular price back on bundle lines
     *    whose bundle is no longer complete. A customer who removes one product
     *    from a bundle must stop keeping the discount on what is left, otherwise
     *    they can strip a bundle down to a single product and still pay the
     *    bundle price. It only rewrites the in-memory model, so a GET never
     *    writes to the carts table.
     *
     * 2. summarize() then works out any bundle discount that is earned by the
     *    ordinary product lines sitting in the cart.
     */
    private function respond(Request $request, string $message = 'OK', int $status = Response::HTTP_OK): JsonResponse
    {
        $cart = $this->comboService->applyExplicitComboPrices($this->query($request)->get());
        $summary = $this->comboService->summarize($cart);

        return $this->ok(
            CartResource::collection($this->annotate($cart, $summary)),
            $this->totals($cart, $summary),
            $message,
            $status,
        );
    }

    /**
     * The customer's lines, with everything a cart row needs to render.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Cart>
     */
    private function query(Request $request)
    {
        return Cart::query()
            ->where('user_id', $request->user()->id)
            // variations and combo are needed by ComboService::regularUnitPrice()
            // and by the resource. Without them a variable product's pre-combo
            // price would fall back to the product's cheapest variation and quote
            // the wrong "was" figure.
            ->with(['product.images', 'product.brand', 'product.variations', 'variation', 'combo'])
            ->orderByDesc('id');
    }

    /**
     * Fetch a cart line, refusing one that belongs to another account.
     */
    private function ownedLine(Request $request, int $cartId): Cart
    {
        return Cart::where('user_id', $request->user()->id)
            ->whereKey($cartId)
            ->firstOrFail();
    }

    /**
     * Attach the derived per-line figures the resource renders.
     *
     * These are set as model attributes rather than passed to the resource so
     * the resource stays a plain map of one cart line. They are synced straight
     * back to "original" so a later save() can never try to write them to a
     * column that does not exist - the same guard ComboService uses when it
     * rewrites a broken combo line's price in memory.
     *
     * @param  \Illuminate\Support\Collection<int, Cart>  $cart
     * @param  array<string, mixed>  $summary
     * @return \Illuminate\Support\Collection<int, Cart>
     */
    private function annotate(Collection $cart, array $summary): Collection
    {
        foreach ($cart as $line) {
            $allocation = $summary['line_allocations'][(string) $line->id] ?? null;

            $line->setAttribute('regular_unit_price', round($this->comboService->regularUnitPrice($line), 2));
            $line->setAttribute('charged_line_total', $this->comboService->chargedLineTotal($line, $summary));
            $line->setAttribute('combo_units', (int) ($allocation['combo_units'] ?? 0));
            $line->setAttribute('combo_names', $allocation['combo_names'] ?? []);

            $line->syncOriginalAttribute('regular_unit_price');
            $line->syncOriginalAttribute('charged_line_total');
            $line->syncOriginalAttribute('combo_units');
            $line->syncOriginalAttribute('combo_names');
        }

        return $cart;
    }

    /**
     * Cart-level totals.
     *
     * `subtotal` is the sum of the line prices and `combo_discount` is what the
     * bundle offers took off, so a client can show the saving and reconcile the
     * arithmetic. Shipping and tax are still left to checkout: quoting them here
     * as a "total" that later changed would be misleading.
     *
     * @param  \Illuminate\Support\Collection<int, Cart>  $cart
     * @param  array<string, mixed>  $summary
     * @return array{subtotal: float, combo_discount: float, total: float, count: int}
     */
    private function totals(Collection $cart, array $summary): array
    {
        $subtotal = round((float) $cart->sum(fn (Cart $line) => (float) $line->subtotal), 2);
        $comboDiscount = round((float) ($summary['discount'] ?? 0), 2);

        return [
            'subtotal' => $subtotal,
            'combo_discount' => $comboDiscount,
            'total' => round(max(0, $subtotal - $comboDiscount), 2),
            'count' => (int) $cart->sum(fn (Cart $line) => (int) $line->quantity),
        ];
    }

    /**
     * Reject a line that cannot be bought.
     *
     * Stock is only enforced when the product manages it; `manage_stock = false`
     * means the shop sells to order.
     */
    private function isSoldOut(Product $product, ?ProductVariation $variation): bool
    {
        if ($variation) {
            return (int) $variation->stock < 1;
        }

        if (! $product->manage_stock) {
            return false;
        }

        return (int) $product->getTotalStockAttribute() < 1;
    }

    /** Remove one line. */
    public function destroy(Request $request, int $cart): JsonResponse
    {
        $this->ownedLine($request, $cart)->delete();

        return $this->respond($request, 'Item removed');
    }

    /** Empty the cart. */
    public function clear(Request $request): JsonResponse
    {
        Cart::where('user_id', $request->user()->id)->delete();

        return $this->ok(
            [],
            ['subtotal' => 0.0, 'combo_discount' => 0.0, 'total' => 0.0, 'count' => 0],
            'Cart cleared'
        );
    }
}
