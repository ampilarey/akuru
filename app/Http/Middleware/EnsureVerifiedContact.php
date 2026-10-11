<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedContact
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', __('signin.error_sign_in_first'));
        }
        if (! $user->hasVerifiedContact()) {
            return redirect()->route('public.courses.index')
                ->with('error', __('signin.error_verify_contact'));
        }

        return $next($request);
    }
}
