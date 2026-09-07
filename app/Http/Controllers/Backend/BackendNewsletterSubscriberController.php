<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BackendNewsletterSubscriberController extends Controller
{
    public function index(): View
    {
        $subscribers = NewsletterSubscriber::query()
            ->orderByDesc('subscribed_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('backend.newsletter_subscribers.index', compact('subscribers'));
    }

    public function destroy(NewsletterSubscriber $newsletterSubscriber): RedirectResponse
    {
        $newsletterSubscriber->delete();

        return back()->with('success', 'Subscriber removed successfully.');
    }
}
