<?php

namespace App\Domains\Bookshop\Actions\Cart;

use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use Illuminate\Support\Facades\DB;

/**
 * The one basket a request is working with (BOOKSHOP_PLAN §4): the signed-in
 * person's, or a guest's by the token in their session. When a guest who
 * has a basket signs in, it merges into their own — quantities add, the
 * guest basket goes — so nothing chosen before signing in is lost.
 */
class ResolveCartAction
{
    /** Where a guest's cart token lives in their session. */
    public const SESSION_KEY = 'bookshop.cart_token';

    public function execute(?int $userId, ?string $sessionToken, bool $create = true): ?Cart
    {
        $guest = $sessionToken !== null && $sessionToken !== ''
            ? Cart::query()->whereNull('user_id')->where('session_token', $sessionToken)->first()
            : null;

        if ($userId === null) {
            if ($guest === null && $create && $sessionToken !== null && $sessionToken !== '') {
                $guest = Cart::query()->create(['session_token' => $sessionToken]);
            }

            return $guest;
        }

        $own = Cart::query()->where('user_id', $userId)->first();
        if ($guest !== null) {
            $own = $this->merge($guest, $own, $userId);
        }
        if ($own === null && $create) {
            $own = Cart::query()->create(['user_id' => $userId]);
        }

        return $own;
    }

    private function merge(Cart $guest, ?Cart $own, int $userId): Cart
    {
        return DB::transaction(function () use ($guest, $own, $userId) {
            if ($own === null) {
                $guest->update(['user_id' => $userId, 'session_token' => null]);

                return $guest;
            }

            foreach ($guest->items as $item) {
                $existing = CartItem::query()
                    ->where('cart_id', $own->id)
                    ->where('product_id', $item->product_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();
                if ($existing !== null) {
                    $existing->update(['quantity' => $existing->quantity + $item->quantity]);
                } else {
                    CartItem::query()->create([
                        'cart_id' => $own->id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                    ]);
                }
            }
            $guest->delete();

            return $own;
        });
    }

    /** How many things are in the basket, for the phone bar and the header. */
    public function count(?int $userId, ?string $sessionToken): int
    {
        $cart = $this->execute($userId, $sessionToken, create: false);

        return $cart === null ? 0 : (int) $cart->items()->sum('quantity');
    }
}
