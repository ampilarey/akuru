<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;

/**
 * Abandoned-cart reminders (BOOKSHOP_PLAN B9 "later, on request", slice
 * B9c). A signed-in customer's cart untouched for a day — and not older
 * than a week — gets one reminder, in the app and by email where the
 * office's customer-email switch allows. Once per cart until they touch it
 * again; never after they placed an order since; never for a guest's cart
 * (there is nobody to tell). Someone who switched shop notices off gets
 * nothing: the one preference covers it.
 */
class RemindAbandonedCartsAction
{
    public function execute(): int
    {
        if (! config('bookshop.cart_reminders.enabled', true)) {
            return 0;
        }
        $quiet = now()->subHours((int) config('bookshop.cart_reminders.after_hours', 24));
        $oldest = now()->subDays((int) config('bookshop.cart_reminders.within_days', 7));
        $sent = 0;

        $carts = Cart::query()->whereNotNull('user_id')->whereHas('items')->with(['items.product.vendor'])->get();
        foreach ($carts as $cart) {
            $last = $cart->items->max(fn (CartItem $i) => $i->updated_at ?? $i->created_at);
            if ($last === null || $last->gt($quiet) || $last->lt($oldest)) {
                continue;
            }
            if ($cart->reminded_at !== null && $cart->reminded_at->gte($last)) {
                continue;
            }
            if (BookshopCheckout::query()->where('user_id', $cart->user_id)->where('created_at', '>=', $last)->exists()) {
                continue;
            }
            $items = $cart->items->filter(fn (CartItem $i) => $i->product !== null);
            if ($items->isEmpty()) {
                continue;
            }
            $shops = $items->map(fn (CartItem $i) => $i->product->vendor?->name)->filter()->unique()->values()->all();
            app(NotifyBookshopUserAction::class)->execute(
                (int) $cart->user_id,
                __('shop.notice_cart_reminder_title'),
                __('shop.notice_cart_reminder_body', ['count' => (int) $items->sum('quantity'), 'shops' => implode(', ', $shops)]),
                '/shop/cart',
                'cart_reminder',
            );
            $cart->forceFill(['reminded_at' => now()])->saveQuietly();
            $sent++;
        }

        return $sent;
    }
}
