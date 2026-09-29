<?php

namespace App\Domains\Bookshop\Actions\Cart;

use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Support\ShopPresenter;
use Illuminate\Validation\ValidationException;

/**
 * "Add the whole list" (STATUS §5lc): every item of a school's book list
 * that is for sale goes into the cart in the quantity the list asks for,
 * through the same `SaveCartItemAction` as a single add — so stock, holiday
 * mode and the per-line limit are checked the same way. An item that
 * cannot go in (sold out, has options to choose) is skipped and named, and
 * the rest still go in.
 */
class AddBookListToCartAction
{
    public function __construct(private SaveCartItemAction $items) {}

    /** An active shop's active book list, or null. */
    public function find(string $vendorSlug, string $listSlug): ?VendorCollection
    {
        return VendorCollection::query()
            ->where('slug', $listSlug)->where('is_active', true)->where('book_list', true)->whereNull('rule')
            ->whereHas('vendor', fn ($v) => $v->where('slug', $vendorSlug)->where('status', VendorStatus::Active->value))
            ->first();
    }

    /**
     * @return array{added: int, skipped: list<string>}
     */
    public function execute(Cart $cart, VendorCollection $list): array
    {
        $forSale = $list->forSaleQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $added = 0;
        $skipped = [];

        foreach ($list->products()->with('variants')->get() as $product) {
            /** @var Product $product */
            if (! in_array((int) $product->id, $forSale, true)) {
                $skipped[] = ShopPresenter::localized($product, 'title');

                continue;
            }
            try {
                $this->items->add($cart, $product->slug, null, max(1, (int) ($product->pivot->quantity ?? 1)));
                $added++;
            } catch (ValidationException) {
                $skipped[] = ShopPresenter::localized($product, 'title');
            }
        }

        return ['added' => $added, 'skipped' => array_values(array_filter($skipped))];
    }
}
