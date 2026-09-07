<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FrontendWishlistController extends Controller
{
    public function index(): View
    {
        if (! Auth::check()) {
            $items = $this->guestWishlistItems();

            return view('frontend.wishlist.index', compact('items'));
        }

        $items = Wishlist::with('product.images', 'product.category', 'variation')
            ->where('user_id', Auth::id())
            ->latest('id')
            ->paginate(20);

        return view('frontend.wishlist.index', compact('items'));
    }

    /**
     * Build wishlist items for guests from the session (product-level only).
     */
    private function guestWishlistItems()
    {
        $ids = array_map('intval', (array) (session('guest_wishlist', []) ?? []));

        if (empty($ids)) {
            return collect();
        }

        return Product::where('is_active', true)
            ->whereIn('id', $ids)
            ->with('images')
            ->get()
            ->map(fn (Product $product) => (object) [
                'id' => 'p' . $product->id,
                'product' => $product,
                'variation' => null,
                'product_variation_id' => null,
            ])
            ->values();
    }

    public function toggle(Request $request)
    {
        if (! Auth::check()) {
            return $this->toggleGuest($request);
        }

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variation_id' => 'nullable|integer|exists:product_variations,id',
        ]);

        $product = Product::with(['variations' => fn ($q) => $q->where('is_active', true)])
            ->findOrFail((int) $data['product_id']);

        $hasActiveVariations = $product->variations->isNotEmpty();
        $variationId = null;

        if ($hasActiveVariations) {
            if (empty($data['product_variation_id'])) {
                return back()->with('error', 'Please select a variation before adding to wishlist.');
            }

            $variation = ProductVariation::query()
                ->where('id', (int) $data['product_variation_id'])
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->first();

            if (! $variation) {
                return back()->with('error', 'Selected variation is invalid or unavailable.');
            }

            $variationId = $variation->id;
        }

        $existing = Wishlist::where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->where('product_variation_id', $variationId)
            ->first();

        if ($existing) {
            $existing->delete();
            $totalCount = Wishlist::where('user_id', Auth::id())->count();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'action' => 'removed',
                    'message' => 'Removed from wishlist.',
                    'total_count' => $totalCount
                ]);
            }

            return back()->with('success', 'Removed from wishlist.');
        }

        Wishlist::create([
            'user_id' => Auth::id(),
            'product_id' => $product->id,
            'product_variation_id' => $variationId,
        ]);

        $totalCount = Wishlist::where('user_id', Auth::id())->count();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'action' => 'added',
                'message' => 'Added to wishlist.',
                'total_count' => $totalCount
            ]);
        }

        return back()->with('success', 'Added to wishlist.');
    }

    public function moveToCart(Request $request)
    {
        if (! Auth::check()) {
            return $this->moveGuestWishlistToCart($request);
        }

        $data = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $userId = Auth::id();
        $movedCount = 0;

        foreach ($data['product_ids'] as $productId) {
            // Find the wishlist entry
            $wishlistItem = Wishlist::where('user_id', $userId)
                ->where('product_id', $productId)
                ->first();

            if (! $wishlistItem) {
                continue;
            }

            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $variationId = $wishlistItem->product_variation_id;
            $unitPrice = $variationId
                ? (float) optional(\App\Models\ProductVariation::find($variationId))->price
                : (float) ($product->sale_price ?? $product->base_price);

            // Add to cart (increment if exists)
            $cartItem = \App\Models\Cart::where('user_id', $userId)
                ->where('product_id', $productId)
                ->where('product_variation_id', $variationId)
                ->first();

            if ($cartItem) {
                $cartItem->quantity += 1;
                $cartItem->save();
            } else {
                \App\Models\Cart::create([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'product_variation_id' => $variationId,
                    'quantity' => 1,
                    'price' => $unitPrice,
                ]);
            }

            // Remove from wishlist
            $wishlistItem->delete();
            $movedCount++;
        }

        $newWishlistCount = Wishlist::where('user_id', $userId)->count();
        $newCartCount = (int) \App\Models\Cart::where('user_id', $userId)->sum('quantity');

        return response()->json([
            'success' => true,
            'moved_count' => $movedCount,
            'wishlist_count' => $newWishlistCount,
            'cart_count' => $newCartCount,
            'message' => $movedCount . ' item(s) moved to cart!',
        ]);
    }

    public function clear(Request $request)
    {
        if (! Auth::check()) {
            return $this->clearGuestWishlist($request);
        }

        $userId = Auth::id();
        $productIds = $request->input('product_ids', []);

        if (!empty($productIds)) {
            // Remove only selected products
            $deleted = Wishlist::where('user_id', $userId)
                ->whereIn('product_id', $productIds)
                ->delete();
            $message = $deleted . ' item(s) removed from wishlist.';
        } else {
            // Remove all
            $deleted = Wishlist::where('user_id', $userId)->delete();
            $message = 'Wishlist cleared!';
        }

        $newCount = Wishlist::where('user_id', $userId)->count();

        return response()->json([
            'success' => true,
            'removed_count' => $deleted,
            'wishlist_count' => $newCount,
            'message' => $message,
        ]);
    }

    /* ───── Guest (no login) helpers ───── */

    private function guestWishlistIds(): array
    {
        return (array) (session('guest_wishlist', []) ?? []);
    }

    private function saveGuestWishlist(array $ids): void
    {
        $normalized = [];
        foreach ($ids as $id) {
            $normalized[] = (int) $id;
        }

        session(['guest_wishlist' => $normalized]);
    }

    private function toggleGuest(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $productId = (int) $data['product_id'];
        $ids = $this->guestWishlistIds();

        $exists = false;
        foreach ($ids as $id) {
            if ((int) $id === $productId) {
                $exists = true;
                break;
            }
        }

        $action = 'added';
        $message = 'Added to wishlist.';

        if ($exists) {
            $remaining = [];
            foreach ($ids as $id) {
                if ((int) $id !== $productId) {
                    $remaining[] = (int) $id;
                }
            }
            $ids = $remaining;
            $action = 'removed';
            $message = 'Removed from wishlist.';
        } else {
            $ids[] = $productId;
        }

        $this->saveGuestWishlist($ids);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'action' => $action,
                'message' => $message,
                'total_count' => count($ids),
            ]);
        }

        return back()->with('success', $message);
    }

    private function moveGuestWishlistToCart(Request $request)
    {
        $data = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $ids = $this->guestWishlistIds();
        $entries = (array) (session('guest_cart', []) ?? []);
        $moved = 0;

        foreach ($data['product_ids'] as $productId) {
            $productId = (int) $productId;

            $stillSaved = false;
            foreach ($ids as $id) {
                if ((int) $id === $productId) {
                    $stillSaved = true;
                    break;
                }
            }

            if (! $stillSaved) {
                continue;
            }

            $product = Product::find($productId);
            if (! $product) {
                continue;
            }

            $key = 'p' . $productId;
            $unitPrice = (float) ($product->sale_price ?? $product->base_price);

            $found = false;
            foreach ($entries as $i => $entry) {
                if (($entry['key'] ?? '') === $key) {
                    $entries[$i]['quantity'] = (int) ($entries[$i]['quantity'] ?? 1) + 1;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $entries[] = [
                    'key' => $key,
                    'product_id' => $productId,
                    'product_variation_id' => null,
                    'quantity' => 1,
                    'price' => $unitPrice,
                ];
            }

            $remaining = [];
            foreach ($ids as $id) {
                if ((int) $id !== $productId) {
                    $remaining[] = (int) $id;
                }
            }
            $ids = $remaining;
            $moved++;
        }

        $this->saveGuestWishlist($ids);
        session(['guest_cart' => $entries]);

        return response()->json([
            'success' => true,
            'moved_count' => $moved,
            'wishlist_count' => count($ids),
            'cart_count' => count($entries),
            'message' => $moved . ' item(s) moved to cart!',
        ]);
    }

    private function clearGuestWishlist(Request $request)
    {
        $ids = $this->guestWishlistIds();
        $asked = $request->input('product_ids', []);

        $remaining = [];
        $removed = 0;

        if (! empty($asked) && is_array($asked)) {
            foreach ($ids as $id) {
                $shouldRemove = false;
                foreach ($asked as $askedId) {
                    if ((int) $id === (int) $askedId) {
                        $shouldRemove = true;
                        break;
                    }
                }

                if ($shouldRemove) {
                    $removed++;
                } else {
                    $remaining[] = (int) $id;
                }
            }

            $message = $removed . ' item(s) removed from wishlist.';
        } else {
            $message = 'Wishlist cleared!';
        }

        $this->saveGuestWishlist($remaining);

        return response()->json([
            'success' => true,
            'removed_count' => $removed,
            'wishlist_count' => count($remaining),
            'message' => $message,
        ]);
    }
}
