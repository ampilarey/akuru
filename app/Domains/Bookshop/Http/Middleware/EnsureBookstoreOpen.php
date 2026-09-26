<?php

namespace App\Domains\Bookshop\Http\Middleware;

use App\Domains\Bookshop\Actions\ShopOpenAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BOOKSHOP_PLAN §7 "shop on/off" (slice B11): while the office has closed
 * the shop, the public shop pages — browsing, the cart, the checkout —
 * answer with the closed notice (503, so nothing indexes it). What a
 * customer already has stays reachable: their orders, quotes, wishlist,
 * a checkout's status and slip, a newsletter link. Whoever runs the
 * Bookstore sees the shop as usual, to check it before reopening.
 */
class EnsureBookstoreOpen
{
    private const STILL_OPEN = ['public.shop.orders', 'public.shop.quotes', 'public.shop.wishlist', 'public.shop.checkout.status', 'public.shop.slip', 'public.shop.newsletter.unsubscribe'];

    public function handle(Request $request, Closure $next): Response
    {
        $name = (string) ($request->route()?->getName() ?? '');
        if (! str_starts_with($name, 'public.shop.') || $this->stillOpen($name) || $request->user()?->can('bookshop.manage')) {
            return $next($request);
        }
        $shop = app(ShopOpenAction::class);
        if ($shop->isOpen()) {
            return $next($request);
        }

        return response()->view('public.shop.closed', ['message' => $shop->message()], 503);
    }

    private function stillOpen(string $name): bool
    {
        foreach (self::STILL_OPEN as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix.'.')) {
                return true;
            }
        }

        return false;
    }
}
