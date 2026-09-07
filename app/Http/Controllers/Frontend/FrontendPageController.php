<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Page;

class FrontendPageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->firstOrFail();

        return view('frontend.page.show', compact('page'));
    }
}
