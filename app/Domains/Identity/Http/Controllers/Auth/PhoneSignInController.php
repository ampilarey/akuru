<?php

namespace App\Domains\Identity\Http\Controllers\Auth;

use App\Domains\Identity\Actions\PhoneSignInAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * COMMERCE_PARITY_PLAN P1: the customer's sign-in, on the phone number.
 * One page in three states — the number, then the password (posted to the
 * ordinary login, which takes a phone) or the code. What was decided about
 * the number sits in the session between the steps, never in the form.
 */
class PhoneSignInController extends Controller
{
    private const SESSION = 'phone_sign_in';

    public function show(Request $request): View
    {
        // Where to go after signing in: a path on this site only, never another host.
        $next = (string) $request->query('next', '');
        if ($next !== '' && str_starts_with($next, '/') && ! str_starts_with($next, '//') && ! str_contains($next, '\\')) {
            $request->session()->put('url.intended', url($next));
        }
        $state = (array) $request->session()->get(self::SESSION, []);
        if ($request->boolean('again')) {
            $request->session()->forget(self::SESSION);
            $state = [];
        }

        return view('auth.phone-sign-in', [
            'mode' => $state['mode'] ?? null,
            'phone' => $state['phone'] ?? null,
            'known' => (bool) ($state['known'] ?? false),
            'codesAvailable' => PhoneSignInAction::codesAvailable(),
        ]);
    }

    public function check(Request $request): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $result = app(PhoneSignInAction::class)->start($data['phone']);
        $request->session()->put(self::SESSION, $result);

        return redirect()->route('phone.sign-in');
    }

    public function verify(Request $request): RedirectResponse
    {
        $state = (array) $request->session()->get(self::SESSION, []);
        if (($state['mode'] ?? null) !== PhoneSignInAction::CODE) {
            return redirect()->route('phone.sign-in', ['again' => 1]);
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = app(PhoneSignInAction::class)->verify((string) $state['phone'], $data['code'], $data['name'] ?? null);

        $request->session()->forget(self::SESSION);
        if ($user->hasTwoFactor()) {
            return TwoFactorChallengeController::begin($request, $user, true);
        }
        Auth::login($user, true);
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        // Bake & Grill's "complete your profile": no password yet, so set one now;
        // the page sends them on to where they were going.
        if ($user->force_password_change) {
            return redirect()->route('account.set-password')->with('success', __('account.phone_set_password_now'));
        }

        return redirect()->intended(route('dashboard'));
    }
}
