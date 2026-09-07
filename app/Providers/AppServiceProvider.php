<?php

namespace App\Providers;

use App\Models\Cart;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
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

        if ($setting && $setting->smtp_host) {
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', $setting->smtp_host);
            Config::set('mail.mailers.smtp.port', (int) ($setting->smtp_port ?: 587));
            Config::set('mail.mailers.smtp.username', $setting->smtp_username);
            Config::set('mail.mailers.smtp.password', $setting->smtp_password);
            Config::set('mail.mailers.smtp.scheme', $setting->smtp_encryption ?: null);
            Config::set('mail.from.address', $setting->smtp_from_email ?: config('mail.from.address'));
            Config::set('mail.from.name', $setting->smtp_from_name ?: config('mail.from.name'));
        }

        View::composer(['layouts.frontend', 'layouts.navbar'], function ($view): void {
            if (Auth::check()) {
                $cartCount = (int) Cart::where('user_id', Auth::id())->sum('quantity');
                $wishlistCount = (int) Wishlist::where('user_id', Auth::id())
                    ->distinct('product_id')
                    ->count('product_id');
            } else {
                // Guests keep their cart & wishlist in the session.
                $cartCount = (int) collect((array) session('guest_cart', []))
                    ->sum(fn ($entry) => (int) ($entry['quantity'] ?? 1));
                $wishlistCount = count((array) session('guest_wishlist', []));
            }

            $view->with('headerCartCount', $cartCount)
                ->with('headerWishlistCount', $wishlistCount);
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
