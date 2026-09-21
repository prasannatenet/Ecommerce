<?php

namespace App\Providers;

use App\Models\Cart;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $setting = Schema::hasTable('settings') ? Setting::query()->first() : null;

        View::share('appSetting', $setting);

        // The admin "SMTP Configuration" section is the single source of truth
        // for outgoing mail. Setting::applyMailConfig() holds the one and only
        // settings => mail config mapping; nothing else may duplicate it.
        if ($setting && $setting->smtp_host) {
            $setting->applyMailConfig();
        }

        View::composer(['layouts.frontend', 'layouts.navbar'], function ($view): void {
            if (Auth::check()) {
                $cartRows = Cart::where('user_id', Auth::id())
                    ->get(['product_id', 'product_variation_id', 'quantity']);
                $wishlistCount = (int) Wishlist::where('user_id', Auth::id())
                    ->distinct('product_id')
                    ->count('product_id');
            } else {
                // Guests keep their cart & wishlist in the session.
                $cartRows = collect(array_values((array) session('guest_cart', [])));
                $wishlistCount = count((array) session('guest_wishlist', []));
            }

            $cartCount = (int) $cartRows->sum(fn ($row) => (int) (
                is_array($row) ? ($row['quantity'] ?? 1) : $row->quantity
            ));

            // Quantities of simple (variation-free) products keyed by product id.
            // Exposed to the layout so product-card counters render instantly on
            // page load instead of waiting for the /cart/quantities AJAX call.
            $simpleCartQuantities = [];
            foreach ($cartRows as $row) {
                $variationId = is_array($row)
                    ? ($row['product_variation_id'] ?? null)
                    : $row->product_variation_id;
                if (! empty($variationId)) {
                    continue;
                }

                $productId = (int) (is_array($row) ? ($row['product_id'] ?? 0) : $row->product_id);
                $quantity = (int) (is_array($row) ? ($row['quantity'] ?? 0) : $row->quantity);
                if ($productId > 0 && $quantity > 0) {
                    $key = (string) $productId;
                    $simpleCartQuantities[$key] = ($simpleCartQuantities[$key] ?? 0) + $quantity;
                }
            }

            $view->with('headerCartCount', $cartCount)
                ->with('headerWishlistCount', $wishlistCount)
                ->with('simpleCartQuantities', $simpleCartQuantities);
        });

        View::composer(['layouts.footer'], function ($view): void {
            $aboutUsPage = null;

            if (Schema::hasTable('pages')) {
                $aboutUsPage = Page::query()
                    ->where('is_active', true)
                    ->where(function ($query): void {
                        $query->whereIn('slug', ['about-us', 'aboutus', 'about_us', 'about'])
                            ->orWhere('title', 'like', '%about%');
                    })
                    ->latest('id')
                    ->first();
            }

            $view->with('aboutUsPage', $aboutUsPage);
        });

        View::composer(['layouts.topbar'], function ($view): void {
            $contactPage = null;

            if (Schema::hasTable('pages')) {
                $contactPage = Page::query()
                    ->where('is_active', true)
                    ->where(function ($query): void {
                        $query->whereIn('slug', ['contact', 'contact-us', 'contactus', 'contact_us'])
                            ->orWhere('title', 'like', '%contact%');
                    })
                    ->latest('id')
                    ->first();
            }

            $view->with('contactPage', $contactPage);
        });
    }
}
