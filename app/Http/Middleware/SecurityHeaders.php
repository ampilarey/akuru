<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // StreamedResponse (CSV exports) has no header() helper — set on the bag.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // `microphone=()` is an **empty allowlist**: it denies every origin,
        // including this one. It closed the only browser API Arabic B's student
        // surface has — `/learn/pronounce` calls `getUserMedia({audio: true})`
        // and got `NotAllowedError` on every browser, for every student, with
        // no setting a student could change to fix it (STATUS §5ej).
        //
        // It arrived with the E8 pick-up slice, which had nothing to do with
        // recording, and no test named this header, so nothing noticed.
        //
        // `(self)` lets this origin ask — the student is still prompted by the
        // browser and can still refuse. Geolocation and camera stay shut
        // because nothing in the app asks for either; if something ever does,
        // it opens its own door in its own slice.
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(self)');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }
}
