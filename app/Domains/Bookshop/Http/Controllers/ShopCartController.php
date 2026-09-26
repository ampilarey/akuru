<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Cart\PresentCartAction;
use App\Domains\Bookshop\Actions\Cart\SaveCartItemAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\ResolvesCart;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BOOKSHOP_PLAN slice B2: the cart. Guests may fill one — it is theirs by a
 * session token and merges into their own when they sign in — so these
 * routes are public and throttled; every write is scoped to the caller's
 * own basket by construction (`ResolvesCart`).
 */
class ShopCartController extends Controller
{
    use ResolvesCart;

    public function index(Request $request)
    {
        return view('public.shop.cart', [
            'cart' => app(PresentCartAction::class)->execute($this->cart($request)),
            'signed_in' => $request->user() !== null,
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product' => 'required|string|max:120',
            'variant_id' => 'nullable|integer',
            'quantity' => 'nullable|integer|min:1|max:'.(int) config('bookshop.checkout.max_quantity_per_line', 50),
        ]);

        $item = app(SaveCartItemAction::class)->add(
            $this->cart($request, create: true),
            $data['product'],
            isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            (int) ($data['quantity'] ?? 1),
        );

        return redirect()->route('public.shop.cart')->with('success', __('shop.added_to_cart_flash', ['title' => $item->product->title]));
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:'.(int) config('bookshop.checkout.max_quantity_per_line', 50)]);
        $cart = $this->cart($request);
        abort_if($cart === null, 404);

        $kept = app(SaveCartItemAction::class)->update($cart, $item, (int) $data['quantity']);

        return back()->with('success', $kept === null ? __('shop.item_removed_flash') : __('shop.cart_updated_flash'));
    }
}
