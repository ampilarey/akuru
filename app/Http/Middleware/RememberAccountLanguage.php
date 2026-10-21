<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LA1 (ADR-044, STATUS §5ug): a signed-in person's language is the one they
 * last opened a page in. Kept on the account, so an email or an SMS written
 * to them later — by the nightly jobs, or by the office working in English —
 * goes in it. Written only when it changes.
 */
class RememberAccountLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $locale = app()->getLocale();
        if ($user !== null && $request->isMethod('GET') && in_array($locale, ['en', 'dv', 'ar'], true) && $user->getAttribute('locale') !== $locale) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }

        return $response;
    }
}
