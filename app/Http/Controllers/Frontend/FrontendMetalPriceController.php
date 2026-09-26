<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\MetalPriceService;
use Illuminate\Http\JsonResponse;

class FrontendMetalPriceController extends Controller
{
    public function __construct(
        private readonly MetalPriceService $metalPrices,
    ) {
    }

    /**
     * The same payload the top bar renders, so the browser can refresh the
     * figures in place without reloading the page.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->metalPrices->displayPayload());
    }
}
