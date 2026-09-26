<?php

namespace App\Domains\Bookshop\Http\Middleware;

use App\Domains\Bookshop\Actions\Shop\ResolveShopHostAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BOOKSHOP_PLAN slice B9f: a request that arrives on the whole-shop
 * subdomain or on a shop's own domain is sent to the same page on the one
 * canonical site, so the cart, sign-in and payment stay on one origin.
 * Global and first, before sessions: nothing is set on those hosts. Only
 * GET and HEAD are redirected; anything else there is a 404.
 */
class RedirectShopHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        $target = app(ResolveShopHostAction::class)->redirectFor($request->getHost(), $request->getPathInfo(), $request->getQueryString());
        if ($target === null) {
            return $next($request);
        }
        abort_unless($request->isMethod('GET') || $request->isMethod('HEAD'), 404);

        return redirect()->away($target, in_array((int) config('bookshop.hosts.redirect_status'), [301, 302, 307, 308], true) ? (int) config('bookshop.hosts.redirect_status') : 302);
    }
}
