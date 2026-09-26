<?php

namespace App\Domains\Bookshop\Actions\Cart;

use App\Domains\Bookshop\Actions\Insights\RecordShopEventAction;
use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Support\Stock;
use Illuminate\Validation\ValidationException;

/**
 * Put something in the basket, change how many, or take it out
 * (BOOKSHOP_PLAN §4 "stock re-checked on every change"). Only a product
 * for sale can go in; a variant must be the product's own and active; the
 * quantity may not exceed what can be sold right now.
 */
class SaveCartItemAction
{
    public function add(Cart $cart, string $productSlug, ?int $variantId, int $quantity): CartItem
    {
        $product = ListShopProductsAction::forSale()->where('slug', $productSlug)->with('variants')->first();
        if ($product === null) {
            throw ValidationException::withMessages(['product' => __('shop.error_not_for_sale')]);
        }

        $variant = null;
        if ($product->variants->where('is_active', true)->isNotEmpty()) {
            $variant = $variantId !== null ? $product->variants->firstWhere('id', $variantId) : null;
            if ($variant === null || ! $variant->is_active) {
                throw ValidationException::withMessages(['variant' => __('shop.error_choose_option')]);
            }
        }

        $existing = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            // B9d: a quoted line keeps its quoted quantity; more of it is an ordinary line.
            ->whereNull('quote_item_id')
            ->first();
        $wanted = ($existing?->quantity ?? 0) + $quantity;

        $item = $this->set($cart, $existing ?? new CartItem([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
        ]), $wanted);
        // B9e: a step of the shop's funnel.
        app(RecordShopEventAction::class)->count((int) $product->vendor_id, 'cart_add', 'product:'.$product->id);

        return $item;
    }

    public function update(Cart $cart, int $itemId, int $quantity): ?CartItem
    {
        $item = CartItem::query()->where('cart_id', $cart->id)->findOrFail($itemId);
        if ($quantity <= 0) {
            $item->delete();

            return null;
        }
        // B9d: a quote prices a quantity; change it and it is no longer the quote.
        // Once the quote has lapsed the line is an ordinary one again.
        if ($item->quote_item_id !== null && ! ($item->quoteItem()->with('quote')->first()?->quote?->priceHolds() ?? false)) {
            $item->update(['quote_item_id' => null]);
        }
        if ($item->quote_item_id !== null) {
            if ($quantity !== (int) $item->quantity) {
                throw ValidationException::withMessages(['quantity' => __('shop.error_quote_quantity')]);
            }

            return $item;
        }

        return $this->set($cart, $item, $quantity);
    }

    private function set(Cart $cart, CartItem $item, int $quantity): CartItem
    {
        $max = (int) config('bookshop.checkout.max_quantity_per_line', 50);
        $quantity = min($quantity, $max);

        $product = ListShopProductsAction::forSale()->whereKey($item->product_id)->with('vendor')->first();
        if ($product === null) {
            $item->exists && $item->delete();
            throw ValidationException::withMessages(['product' => __('shop.error_not_for_sale')]);
        }
        // B3 holiday mode: the product stays visible; the cart refuses it.
        if ($product->vendor->onHoliday()) {
            throw ValidationException::withMessages(['product' => __('shop.error_on_holiday', ['vendor' => $product->vendor->name, 'date' => $product->vendor->holiday_until->copy()->addDay()->toDateString()])]);
        }
        $variant = $item->product_variant_id !== null ? ProductVariant::query()->find($item->product_variant_id) : null;

        $available = Stock::available($product, $variant);
        if ($available !== null && $quantity > $available && ! Stock::madeToOrder($product)) {
            if ($available <= 0) {
                throw ValidationException::withMessages(['quantity' => __('shop.error_sold_out', ['title' => $product->title])]);
            }
            throw ValidationException::withMessages(['quantity' => __('shop.error_only_n_left', ['count' => $available, 'title' => $product->title])]);
        }

        $item->cart_id = $cart->id;
        $item->quantity = $quantity;
        $item->save();

        return $item;
    }
}
