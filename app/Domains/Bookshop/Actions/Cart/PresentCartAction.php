<?php

namespace App\Domains\Bookshop\Actions\Cart;

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Support\CartPrice;
use App\Domains\Bookshop\Support\SalePrice;
use App\Domains\Bookshop\Support\ShopPresenter;
use App\Domains\Bookshop\Support\Stock;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;

/**
 * The basket as a customer reads it (BOOKSHOP_PLAN §4): lines grouped by
 * vendor with a sub-total each, and every line re-checked against what is
 * for sale now — a product that went off sale or ran out is flagged, not
 * silently dropped, so the customer sees why the checkout refuses.
 */
class PresentCartAction
{
    /**
     * @return array{groups: list<array<string, mixed>>, subtotal: string, currency: string, count: int, problems: list<string>, empty: bool}
     */
    public function execute(?Cart $cart): array
    {
        if ($cart === null) {
            return ['groups' => [], 'subtotal' => '0.00', 'currency' => config('bookshop.currency', 'MVR'), 'count' => 0, 'problems' => [], 'empty' => true, 'saved' => []];
        }

        $items = $cart->items()->with(['product.vendor', 'product.images', 'product.variants', 'variant'])->get();
        $forSale = ListShopProductsAction::forSale()->whereIn('id', $items->pluck('product_id')->all())->pluck('id')->all();
        $images = app(ResolvePublicImageVariantAction::class);

        $groups = [];
        $problems = [];
        $subtotal = 0.0;
        $count = 0;

        foreach ($items as $item) {
            /** @var CartItem $item */
            $product = $item->product;
            $variant = $item->variant;
            $sellable = in_array($product->id, $forSale, true) && ($variant === null || $variant->is_active);
            $available = $sellable ? Stock::available($product, $variant) : 0;
            $short = $available !== null && $item->quantity > $available && ! Stock::madeToOrder($product);
            // B9d: a quoted line pays the quoted price while the quote holds; §5lb: a running sale's price.
            $quoted = CartPrice::quoted($item, $product, $variant);
            $listPrice = (float) ($variant?->price ?? $product->price);
            $unitPrice = CartPrice::unit($item, $product, $variant);
            $lineTotal = round($unitPrice * $item->quantity, 2);

            if (! $sellable) {
                $problems[] = __('shop.error_not_for_sale_named', ['title' => $product->title]);
            } elseif ($product->vendor->onHoliday()) {
                $sellable = false;
                $problems[] = __('shop.error_on_holiday', ['vendor' => $product->vendor->name, 'date' => $product->vendor->holiday_until->copy()->addDay()->toDateString()]);
            } elseif ($short) {
                $problems[] = $available <= 0
                    ? __('shop.error_sold_out', ['title' => $product->title])
                    : __('shop.error_only_n_left', ['count' => $available, 'title' => $product->title]);
            }

            $vendorId = (int) $product->vendor_id;
            $groups[$vendorId] ??= [
                'vendor' => ShopPresenter::vendor($product->vendor),
                'lines' => [],
                'subtotal' => 0.0,
            ];
            $first = $product->images->first();
            $groups[$vendorId]['lines'][] = [
                'id' => $item->id,
                'slug' => $product->slug,
                'title' => ShopPresenter::localized($product, 'title'),
                'variant' => $variant?->name,
                'image' => $first !== null ? $images->execute((int) $first->media_file_id, ShopPresenter::CARD_WIDTH) : null,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                // §5lb: the list price struck through while a sale lowers it.
                'was_price' => $quoted === null && $unitPrice < $listPrice ? number_format($listPrice, 2, '.', '') : null,
                'quantity' => (int) $item->quantity,
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'available' => $available,
                'sellable' => $sellable,
                'short' => $short,
                'made_to_order' => Stock::madeToOrder($product),
                'quoted' => $quoted !== null,
                'quote_lapsed' => $item->quote_item_id !== null && $quoted === null,
            ];
            $groups[$vendorId]['subtotal'] += $lineTotal;
            $subtotal += $lineTotal;
            $count += (int) $item->quantity;
        }

        foreach ($groups as &$group) {
            $group['subtotal'] = number_format($group['subtotal'], 2, '.', '');
        }

        return [
            'groups' => array_values($groups),
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'currency' => config('bookshop.currency', 'MVR'),
            'count' => $count,
            'problems' => $problems,
            'empty' => $items->isEmpty(),
            'saved' => $this->saved($cart),
        ];
    }

    /**
     * §5lf: the lines set aside — today's price (a running sale's), and
     * whether each can go back in now.
     *
     * @return list<array<string, mixed>>
     */
    private function saved(Cart $cart): array
    {
        $saved = $cart->savedItems()->with(['product.vendor', 'product.images', 'variant'])->get();
        $forSale = ListShopProductsAction::forSale()->whereIn('id', $saved->pluck('product_id')->all())->pluck('id')->all();
        $images = app(ResolvePublicImageVariantAction::class);

        return $saved->filter(fn (CartItem $i) => $i->product !== null)->map(function (CartItem $item) use ($forSale, $images) {
            $product = $item->product;
            $available = in_array($product->id, $forSale, true) && ($item->variant === null || $item->variant->is_active)
                ? Stock::available($product, $item->variant) : 0;
            $first = $product->images->first();

            return [
                'id' => $item->id,
                'slug' => $product->slug,
                'title' => ShopPresenter::localized($product, 'title'),
                'variant' => $item->variant?->name,
                'vendor' => $product->vendor?->name,
                'image' => $first !== null ? $images->execute((int) $first->media_file_id, ShopPresenter::CARD_WIDTH) : null,
                'unit_price' => number_format(SalePrice::apply((float) ($item->variant?->price ?? $product->price), $product), 2, '.', ''),
                'quantity' => (int) $item->quantity,
                'can_move' => $available === null || $available > 0 || Stock::madeToOrder($product),
            ];
        })->values()->all();
    }
}
