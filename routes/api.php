<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ComboController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\MetalPriceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON API (prefixed with /api by bootstrap/app.php)
|--------------------------------------------------------------------------
|
| The complete storefront API consumed by the React frontend.
|
| Live on the deployed domain:
|   https://astroemerging.com/gehna/api/v1/...
|
| ## Authentication
|
|   POST /auth/register  -> { token, token_type, user }
|   POST /auth/login     -> { token, token_type, user }
|
| Send the token on every protected call:
|
|   Authorization: Bearer <token>
|
| Tokens are Sanctum personal access tokens. The routes grouped under
| `auth:sanctum` below are the ones that require one.
|
| ## Response envelope
|
|   success  bool     true on 2xx
|   message  string   human readable, safe to show in a toast
|   data     mixed    the payload
|   meta     object   counts, pagination, echoed filters
|   errors   object   per-field validation messages (422 only)
|
| ## Rate limits
|
| Public reads are throttled at 120/min and auth at 10/min: enough for a page
| that fires a dozen calls on mount, still enough to stop a runaway client or
| a credential-stuffing loop.
|
*/

Route::prefix('v1')->name('api.')->group(function () {

    // ── Public: home & site content ────────────────────────────────
    Route::get('home', [ContentController::class, 'home'])->name('home');
    Route::get('sliders', [ContentController::class, 'sliders'])->name('sliders.index');
    Route::get('faqs', [ContentController::class, 'faqs'])->name('faqs.index');
    Route::get('testimonials', [ContentController::class, 'testimonials'])->name('testimonials.index');
    Route::get('settings', [ContentController::class, 'settings'])->name('settings');
    Route::post('newsletter', [ContentController::class, 'newsletter'])->name('newsletter');

    // ── Public: catalogue ──────────────────────────────────────────
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{slug}', [ProductController::class, 'show'])->name('products.show');
    Route::get('products/{slug}/related', [ProductController::class, 'related'])->name('products.related');
    Route::get('products/{slug}/reviews', [ProductController::class, 'reviews'])->name('products.reviews');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    // Registered before categories/{slug} so "tree" is never swallowed as a slug.
    Route::get('categories/tree', [CategoryController::class, 'index'])->name('categories.tree');
    Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('categories/{slug}/products', [CategoryController::class, 'products'])->name('categories.products');

    Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    Route::get('brands/{slug}', [BrandController::class, 'show'])->name('brands.show');
    Route::get('brands/{slug}/products', [BrandController::class, 'products'])->name('brands.products');

    Route::get('combos', [ComboController::class, 'index'])->name('combos.index');
    Route::get('combos/{slug}', [ComboController::class, 'show'])->name('combos.show');

    Route::get('search', SearchController::class)->name('search');

    Route::get('pages', [ContentController::class, 'pages'])->name('pages.index');
    Route::get('pages/{slug}', [ContentController::class, 'page'])->name('pages.show');

    // Live metal spot rates. Throttled tighter than the rest of the catalogue
    // because the figures are cached but the endpoint is public: without a
    // ceiling one client polling in a loop would still be hammering the server
    // between cache windows.
    Route::get('metal-prices', [MetalPriceController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('metal-prices.index');
    Route::get('metal-prices/{metal}', [MetalPriceController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('metal-prices.show');

    // ── Auth ───────────────────────────────────────────────────────
    // Tighter throttle than the reads: 10/min is generous for a human and
    // useless to someone guessing passwords. Login additionally shares the
    // Blade form's lockout (see AuthController).
    Route::prefix('auth')->name('auth.')->middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
    });

    // ── Authenticated ──────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::put('auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile');
        Route::put('auth/password', [AuthController::class, 'updatePassword'])->name('auth.password');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');

        Route::get('cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('cart', [CartController::class, 'store'])->name('cart.store');
        Route::patch('cart/{cart}', [CartController::class, 'update'])->name('cart.update');
        Route::delete('cart/{cart}', [CartController::class, 'destroy'])->name('cart.destroy');
        Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');

        Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
        Route::delete('wishlist/{productId}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
        Route::delete('wishlist', [WishlistController::class, 'clear'])->name('wishlist.clear');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    });
});
