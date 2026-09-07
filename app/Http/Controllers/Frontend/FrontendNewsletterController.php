<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FrontendNewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'newsletter_email' => 'required|email:rfc,dns|max:255',
        ]);

        $subscriber = NewsletterSubscriber::firstOrCreate(
            ['email' => strtolower(trim($data['newsletter_email']))],
            ['subscribed_at' => now()]
        );

        if (! $subscriber->wasRecentlyCreated) {
            return back()->with('newsletter_info', 'This email is already subscribed to our newsletter.');
        }

        return back()->with('newsletter_success', 'Thanks for subscribing to our newsletter.');
    }
}
