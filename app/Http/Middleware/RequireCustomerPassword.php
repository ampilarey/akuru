<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * COMMERCE_PARITY_PLAN P1: Bake & Grill's "complete your profile", made a
 * rule rather than a suggestion (plan §6 no. 3). An account that signed in
 * with a code — its mobile number verified — and still has no password it
 * chose is sent to set one before it buys anything; after that its number
 * takes the password only. A §5ly guest (no verified number) is not: it has
 * no identifier to sign in with, so a password would be of no use to it.
 */
class RequireCustomerPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && $user->force_password_change
            && $user->contacts()->where('type', 'mobile')->whereNotNull('verified_at')->exists()) {
            $request->session()->put('url.intended', $request->isMethod('GET') ? $request->fullUrl() : url()->previous());

            return redirect()->route('account.set-password')->with('success', __('account.phone_set_password_now'));
        }

        return $next($request);
    }
}
