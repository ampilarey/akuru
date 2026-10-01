<?php

namespace App\Domains\Identity\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (! $request->authenticate()) {
            // STATUS §5lk: the password was right; a code from their app finishes it.
            return TwoFactorChallengeController::begin($request, $request->twoFactorUser, $request->boolean('remember'));
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    /**
     * Log out and land on the public home page. The shell's Log out button
     * posts as an Inertia request; the home page is Blade, so a plain
     * redirect would come back as a non-Inertia page and Inertia would show
     * it in a modal over the shell (the owner's screenshot, 2026-10-01).
     * Inertia::location makes the browser do a full visit instead.
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return $request->header('X-Inertia') ? Inertia::location('/') : redirect('/');
    }
}
