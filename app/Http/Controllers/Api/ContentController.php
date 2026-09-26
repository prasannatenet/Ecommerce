<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuildsProductQuery;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\FaqResource;
use App\Http\Resources\PageResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\SliderResource;
use App\Http\Resources\TestimonialResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Faq;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everything on the storefront that is not a product, a category or an order.
 *
 *   GET  /gehna/api/v1/home                one call that fills the landing page
 *   GET  /gehna/api/v1/sliders
 *   GET  /gehna/api/v1/pages
 *   GET  /gehna/api/v1/pages/{slug}
 *   GET  /gehna/api/v1/faqs
 *   GET  /gehna/api/v1/testimonials
 *   GET  /gehna/api/v1/settings            public store settings only
 *   POST /gehna/api/v1/newsletter          subscribe an email address
 */
class ContentController extends ApiController
{
    use BuildsProductQuery;

    /**
     * Landing-page payload.
     *
     * Bundled into one response on purpose: the Blade home page needs ten
     * separate queries, and a React page that fired ten requests on mount would
     * render ten times and burn through the throttle. One round trip, one paint.
     */
    public function home(Request $request): JsonResponse
    {
        $limit = $this->perPage($request, 8);

        $cards = fn () => $this->withCardRelations(
            Product::query()->where('is_active', true)
        )->withCount(['reviews as reviews_count']);

        return $this->ok([
            'sliders' => SliderResource::collection(
                Slider::where('is_active', true)->orderBy('sort_order')->orderByDesc('id')->get()
            )->resolve(),

            'categories' => CategoryResource::collection(
                Category::query()
                    ->whereNull('parent_id')
                    ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                    ->orderBy('position')
                    ->orderBy('name')
                    ->take(10)
                    ->get()
            )->resolve(),

            'new_arrivals' => ProductResource::collection(
                $cards()->latest()->orderByDesc('id')->limit($limit)->get()
            )->resolve(),

            'featured' => ProductResource::collection(
                $cards()->whereNotNull('sale_price')->latest()->limit($limit)->get()
            )->resolve(),

            'on_sale' => ProductResource::collection(
                $cards()
                    ->whereNotNull('sale_price')
                    ->whereColumn('sale_price', '<', 'base_price')
                    ->latest()
                    ->limit($limit)
                    ->get()
            )->resolve(),

            'brands' => BrandResource::collection(
                Brand::withCount(['products' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get()
            )->resolve(),

            'faqs' => FaqResource::collection(
                Faq::where('is_active', true)->orderBy('sort_order')->orderByDesc('id')->get()
            )->resolve(),

            'testimonials' => TestimonialResource::collection(
                Testimonial::where('is_active', true)->orderBy('sort_order')->orderByDesc('id')->get()
            )->resolve(),
        ]);
    }

    public function sliders(): JsonResponse
    {
        return $this->ok(SliderResource::collection(
            Slider::where('is_active', true)->orderBy('sort_order')->orderByDesc('id')->get()
        ));
    }

    public function pages(): JsonResponse
    {
        $pages = Page::where('is_active', true)->orderBy('title')->get();
        $base = $this->storefrontBase();

        // Titles and slugs only: shipping every page's full HTML on the index
        // request would dwarf the payload for data a footer menu never uses.
        return $this->ok($pages->map(fn (Page $page) => [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'url' => $base.route('pages.show', $page->slug, absolute: false),
        ]));
    }

    public function page(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->where('is_active', true)->first();

        if (! $page) {
            return $this->fail('Page not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->ok(new PageResource($page));
    }

    public function faqs(): JsonResponse
    {
        return $this->ok(FaqResource::collection(
            Faq::where('is_active', true)->orderBy('sort_order')->orderByDesc('id')->get()
        ));
    }

    public function testimonials(): JsonResponse
    {
        return $this->ok(TestimonialResource::collection(
            Testimonial::where('is_active', true)->orderBy('sort_order')->orderByDesc('id')->get()
        ));
    }

    /**
     * Public store settings.
     *
     * An explicit allow-list, not `$setting->toArray()`: the settings table
     * also holds smtp_password and resend_api_key, and a blanket dump would
     * publish the shop's mail credentials to every caller of the API.
     */
    public function settings(): JsonResponse
    {
        $setting = Setting::query()->first();

        if (! $setting) {
            return $this->ok([]);
        }

        $base = $this->storefrontBase();

        return $this->ok([
            'site_name' => $setting->site_name,
            'description' => $setting->description,
            'email' => $setting->email,
            'phone' => $setting->phone,
            'address' => $setting->address,
            'city' => $setting->city,
            'state' => $setting->state,
            'country' => $setting->country,
            'zip' => $setting->zip,
            'logo' => $setting->logo_path ? $base.'/storage/'.ltrim($setting->logo_path, '/') : null,
            'favicon' => $setting->favicon_path ? $base.'/storage/'.ltrim($setting->favicon_path, '/') : null,
            'social' => array_filter([
                'facebook' => $setting->facebook_url,
                'instagram' => $setting->instagram_url,
                'twitter' => $setting->twitter_url,
                'youtube' => $setting->youtube_url,
                'linkedin' => $setting->linkedin_url,
            ]),
        ]);
    }

    /**
     * Newsletter sign-up.
     *
     * Already-subscribed is reported as a friendly 200 rather than a 422: the
     * address is simply not added twice, and telling a stranger that a given
     * email is on the list is a small but unnecessary account oracle.
     */
    public function newsletter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $subscriber = NewsletterSubscriber::firstOrCreate(
            ['email' => strtolower(trim($data['email']))],
            ['subscribed_at' => now()],
        );

        if (! $subscriber->wasRecentlyCreated) {
            return $this->ok(
                ['subscribed' => true, 'already_subscribed' => true],
                message: 'This email is already subscribed to our newsletter.',
            );
        }

        return $this->ok(
            ['subscribed' => true, 'already_subscribed' => false],
            message: 'Thanks for subscribing to our newsletter.',
            status: Response::HTTP_CREATED,
        );
    }

    /**
     * Public base URL of the shop, without a trailing slash.
     *
     * STOREFRONT_URL when it is set, otherwise the current request's own root
     * — which is how /gehna is picked up without repeating it in .env. Shared
     * with the resources through ResolvesStorefrontUrls::storefrontUrl().
     */
    private function storefrontBase(): string
    {
        return rtrim((string) (config('storefront.url') ?: url('/')), '/');
    }
}
