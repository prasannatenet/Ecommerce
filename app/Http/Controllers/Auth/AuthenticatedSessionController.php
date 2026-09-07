<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Slider;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        $loginSliderImages = Slider::query()
            ->where('is_active', true)
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->pluck('image_path')
            ->map(fn ($path) => asset('storage/' . ltrim($path, '/')))
            ->values();

        return view('auth.login', compact('loginSliderImages'));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Carry over any guest cart & wishlist items into the freshly authenticated account.
        $this->mergeGuestDataIntoAccount($request->user());

        $user = $request->user();
        $isAdmin = $user && ($user->hasRole('admin') || $user->can('access admin'));

        if ($isAdmin) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        $intended = (string) $request->session()->get('url.intended', '');
        if ($intended !== '' && str_contains($intended, '/admin')) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route('account.index', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Merge session-based guest cart & wishlist items into the authenticated
     * user's account, then clear the guest session data.
     */
    private function mergeGuestDataIntoAccount(User $user): void
    {
        // ── Cart ──
        $entries = (array) (session('guest_cart', []) ?? []);

        foreach ($entries as $entry) {
            $productId = (int) ($entry['product_id'] ?? 0);
            $variationId = $entry['product_variation_id'] ?? null;
            $quantity = max(1, (int) ($entry['quantity'] ?? 1));
            $price = (float) ($entry['price'] ?? 0);

            if ($productId <= 0 || ! Product::find($productId)) {
                continue;
            }

            $existing = Cart::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->where('product_variation_id', $variationId)
                ->first();

            if ($existing) {
                $existing->quantity += $quantity;
                $existing->save();
            } else {
                Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'product_variation_id' => $variationId,
                    'quantity' => $quantity,
                    'price' => $price,
                ]);
            }
        }

        session()->forget('guest_cart');

        // ── Wishlist ──
        $ids = array_map('intval', (array) (session('guest_wishlist', []) ?? []));

        foreach ($ids as $productId) {
            if ($productId <= 0 || ! Product::find($productId)) {
                continue;
            }

            $exists = Wishlist::where('user_id', $user->id)
                ->where('product_id', $productId)
                ->whereNull('product_variation_id')
                ->exists();

            if (! $exists) {
                Wishlist::create([
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'product_variation_id' => null,
                ]);
            }
        }

        session()->forget('guest_wishlist');
    }
}
