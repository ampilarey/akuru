<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Shop\NewsletterAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BOOKSHOP_PLAN slice B9c: a shop's newsletter — sign up on its page, and
 * the unsubscribe page its mailings link to. Public and throttled; the
 * token is the whole of the unsubscribe right, so nothing else is shown.
 */
class NewsletterController extends Controller
{
    public function subscribe(Request $request, string $vendor): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:120',
            'consent' => 'accepted',
        ]);

        app(NewsletterAction::class)->subscribe($vendor, $data['email'], $data['name'] ?? null, true, $request->user()?->id);

        return back()->with('newsletter_joined', $vendor);
    }

    public function show(string $token)
    {
        $subscriber = app(NewsletterAction::class)->find($token);
        abort_if($subscriber === null, 404);

        return view('public.shop.newsletter', ['subscriber' => $subscriber, 'token' => $token, 'done' => (bool) session('newsletter_left')]);
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        abort_if(app(NewsletterAction::class)->find($token) === null, 404);
        app(NewsletterAction::class)->unsubscribe($token);

        return redirect()->route('public.shop.newsletter.unsubscribe', $token)->with('newsletter_left', true);
    }
}
