<?php

namespace App\Domains\Identity\Http\Controllers\Auth;

use App\Domains\Identity\Actions\TwoFactorAction;
use App\Domains\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * STATUS §5lk: the second step. The password (or the OTP) was right, so the
 * session holds who is signing in — not signed in yet — for ten minutes; a
 * code from their app, or a recovery code, finishes it. Five wrong codes a
 * minute per person, then a wait.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION = 'two_factor_login';

    /** Called by the password and OTP sign-ins when the person has it on. */
    public static function begin(Request $request, User $user, bool $remember): RedirectResponse
    {
        $request->session()->put(self::SESSION, ['id' => $user->id, 'remember' => $remember, 'at' => now()->timestamp]);

        return redirect()->route('two-factor.challenge');
    }

    public function create(Request $request)
    {
        if ($this->pending($request) === null) {
            return redirect()->route('login');
        }

        return view('auth.otp-verify', ['twoFactor' => true]);
    }

    public function store(Request $request, TwoFactorAction $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => 'required|string|max:32']);
        $pending = $this->pending($request);
        if ($pending === null) {
            return redirect()->route('login')->withErrors(['identifier' => __('security.error_expired')]);
        }
        $user = User::query()->whereKey($pending['id'])->where('is_active', true)->first();
        $key = 'two-factor:'.$pending['id'];
        if ($user === null) {
            $request->session()->forget(self::SESSION);

            return redirect()->route('login');
        }
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => __('security.error_throttled', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        if (! $twoFactor->verify($user, $data['code'])) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['code' => __('security.error_code')]);
        }

        RateLimiter::clear($key);
        $request->session()->forget(self::SESSION);
        Auth::login($user, (bool) $pending['remember']);
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** @return array{id: int, remember: bool, at: int}|null */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION);
        if (! is_array($pending) || now()->timestamp - (int) ($pending['at'] ?? 0) > 600) {
            return null;
        }

        return $pending;
    }
}
