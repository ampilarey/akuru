<?php

namespace App\Domains\Identity\Http\Controllers\Auth;

use App\Domains\Identity\Actions\StartGuestAccountAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * STATUS §5ly: "Continue as guest" on the cart, a Library item and the gift
 * cards page. A name and a mobile number make an account and sign it in (Bake
 * & Grill's guest checkout), and the buyer goes on to what they were buying.
 */
class GuestCheckoutController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s\-]{7,20}$/'],
            'for' => ['required', 'in:shop,library,gift_card'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9\-]+$/'],
        ]);

        // Bake & Grill's limit: ten a number an hour, from one address.
        $key = 'guest-checkout:'.preg_replace('/\D/', '', $data['guest_phone']).':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withInput()->withErrors(['guest_phone' => __('account.guest_too_many')]);
        }
        RateLimiter::hit($key, 3600);

        $result = app(StartGuestAccountAction::class)->execute($data['guest_name'], $data['guest_phone']);
        if ($result['refused'] !== null) {
            return back()->withInput()->withErrors(['guest_phone' => __('account.guest_'.$result['refused'])]);
        }

        Auth::loginUsingId($result['user_id'], true);
        $request->session()->regenerate();

        return redirect()->to($this->next($data))->with('success', __('account.guest_welcome'));
    }

    /** Where the buyer was going: only these three places, never a URL from the form. */
    private function next(array $data): string
    {
        return match ($data['for']) {
            'shop' => route('public.shop.checkout'),
            'gift_card' => route('public.gift-cards.index'),
            default => filled($data['slug'] ?? null) ? route('public.library.show', $data['slug']) : route('public.library.index'),
        };
    }
}
