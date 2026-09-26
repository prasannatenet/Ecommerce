<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Support\Str;

/**
 * Shared storefront URL building for every API resource.
 *
 * Two problems this solves, both of which only show up once JSON is consumed
 * from a different origin than the API:
 *
 *  1. The shop is installed in a sub-directory (`/gehna`), so a root-relative
 *     `/storage/...` or `/product/...` points at the wrong place entirely.
 *  2. A React app served from localhost:5173 resolves root-relative URLs
 *     against its own dev server, so every image on the page 404s.
 *
 * Every URL the API emits is therefore built from config('storefront.url'),
 * which falls back to APP_URL for local development and the test suite.
 */
trait ResolvesStorefrontUrls
{
    /**
     * Public base URL of the shop, without a trailing slash.
     *
     * e.g. https://astroemerging.com/gehna
     *
     * STOREFRONT_URL wins when it is set. Otherwise the current request's own
     * root is used, which is right for local development, for production, and
     * for an install living in a sub-directory: it detects /gehna from the
     * request rather than needing it repeated in .env.
     */
    protected function storefrontUrl(): string
    {
        return rtrim((string) (config('storefront.url') ?: url('/')), '/');
    }

    /**
     * The install sub-path with slashes on both sides ("/gehna/"), or "/" when
     * the app sits at the domain root.
     *
     * Derived from the configured base URL so it stays correct if the shop is
     * ever moved to a different folder.
     */
    protected function storefrontPath(): string
    {
        $base = parse_url($this->storefrontUrl(), PHP_URL_PATH) ?: '/';

        return '/'.trim($base, '/').'/';
    }

    /**
     * Turn a stored media path into an absolute URL the browser can load.
     *
     * Returns null for empty values so the client can fall back to its own
     * placeholder instead of rendering a broken <img src="">. Values that are
     * already absolute (remote CDN, data URI) are handed back untouched.
     */
    protected function mediaUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//', 'data:', 'blob:'])) {
            return Str::startsWith($path, '//') ? 'https:'.$path : $path;
        }

        return $this->storefrontUrl().'/storage/'.ltrim($path, '/');
    }

    /**
     * Absolute URL of a storefront page (product, category, brand, page).
     */
    protected function pageUrl(string $routeName, mixed $parameters = null): string
    {
        return rtrim($this->storefrontUrl(), '/').route($routeName, $parameters, absolute: false);
    }

    /**
     * Cast a list of stored media paths to absolute URLs, dropping blanks so
     * the payload never carries empty image entries.
     *
     * @param  iterable<int, string|null>  $paths
     * @return array<int, string>
     */
    protected function mediaList(iterable $paths): array
    {
        $urls = [];

        foreach ($paths as $path) {
            $url = $this->mediaUrl(is_string($path) ? $path : null);

            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }
}
