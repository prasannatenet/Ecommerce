<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in customer's cart.
 *
 *   GET    /gehna/api/v1/cart
 *   POST   /gehna/api/v1/cart                 add a product or variation
 *   PATCH  /gehna/api/v1/cart/{id}            change quantity
 *   DELETE /gehna/api/v1/cart/{id}            remove one line
 *   DELETE /gehna/api/v1/cart                 empty the cart
 *
 * Lines are always scoped to $request->user()->id. A cart id from another
 * account therefore 404s instead of being readable or writable, and no
 * endpoint trusts a price from the client: the unit price is resolved from
 * the product and variation rows, so a tampered request cannot set its own
 * price.
 */
class CartController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $cart = $this->query($request)->get();

        return $this->ok(
            CartResource::collection($cart),
            $this->totals($cart),
        );
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

        $cart = $this->query($request)->get();

        return $this->ok(
            CartResource::collection($cart),
            $this->totals($cart),
            'Added to cart',
            Response::HTTP_CREATED,
        );
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

            $cartItems = $this->query($request)->get();

            return $this->ok(CartResource::collection($cartItems), $this->totals($cartItems), 'Item removed');
        }

        $line->quantity = $quantity;
        $line->save();

        $cartItems = $this->query($request)->get();

        return $this->ok(
            CartResource::collection($cartItems),
            $this->totals($cartItems),
            'Cart updated',
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
            ->with(['product.images', 'product.brand', 'variation'])
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
     * Cart-level totals. Only the line sum is exposed here — shipping, tax and
     * coupon discounts are quote options that the checkout endpoint decides,
     * so showing a "total" here that later changes would be misleading.
     *
     * @param  \Illuminate\Support\Collection<int, Cart>  $cart
     * @return array{subtotal: float, total: float, count: int}
     */
    private function totals($cart): array
    {
        $subtotal = round((float) $cart->sum(fn (Cart $line) => $line->subtotal), 2);

        return [
            'subtotal' => $subtotal,
            'total' => $subtotal,
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

        $cartItems = $this->query($request)->get();

        return $this->ok(CartResource::collection($cartItems), $this->totals($cartItems), 'Item removed');
    }

    /** Empty the cart. */
    public function clear(Request $request): JsonResponse
    {
        Cart::where('user_id', $request->user()->id)->delete();

        return $this->ok([], ['subtotal' => 0.0, 'total' => 0.0, 'count' => 0], 'Cart cleared');
    }
}
