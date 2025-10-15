<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // LaravelLocalization package already handles locale from URL
        // This middleware is only needed as a fallback for non-localized routes
        // But since all routes use LaravelLocalization, we just pass through
        
        // The locale is already set by LaravelLocalization middleware
        // via the URL prefix (/en/, /ar/, /dv/)
        
        return $next($request);
    }
}