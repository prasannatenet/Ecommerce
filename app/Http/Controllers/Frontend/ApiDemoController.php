<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * A live page that renders the storefront purely from the JSON API.
 *
 * The view makes no server-side queries at all: it loads the same
 * public/js/gehna-api.js file the React app uses and calls it from the
 * browser. That makes this page a genuine end-to-end check of the API — if an
 * endpoint, a field name or the CORS setup is wrong, the page is visibly
 * empty rather than quietly falling back to server-side data.
 *
 * Route: GET /api-demo
 */
class ApiDemoController extends Controller
{
    public function __invoke(): View
    {
        return view('frontend.api-demo', [
            // url() is request-aware, so the page always calls the API on the
            // origin it was itself served from: http://127.0.0.1:8000/api/v1 in
            // development, https://astroemerging.com/gehna/api/v1 in production.
            // Reading it from config('storefront.url') instead would send a
            // locally-served page to whatever APP_URL happens to say, and the
            // browser would report a network error the developer cannot see.
            'apiBase' => url('/api/'.config('storefront.api_version', 'v1')),

            // The client is served from /js/gehna-api.js and is the exact file
            // intended to be copied into the React project.
            'clientUrl' => asset('js/gehna-api.js'),
        ]);
    }
}
