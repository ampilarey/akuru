<?php

namespace App\Domains\Bookshop\Actions\Checkout;

use App\Domains\Bookshop\Actions\Cart\PresentCartAction;
use App\Domains\Bookshop\Models\Cart;
use App\Domains\Bookshop\Models\CustomerAddress;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Commerce\Actions\ListWalletAction;

/**
 * Everything the checkout page shows (BOOKSHOP_PLAN §4 "Checkout"): the
 * basket by vendor, each vendor's delivery options priced for this basket,
 * the address book, the payment methods on offer and the wallet balance.
 */
class PrepareCheckoutAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId, ?Cart $cart): array
    {
        $basket = app(PresentCartAction::class)->execute($cart);

        $vendors = Vendor::query()->whereIn('slug', array_column(array_column($basket['groups'], 'vendor'), 'slug'))->get()->keyBy('slug');
        foreach ($basket['groups'] as &$group) {
            $vendor = $vendors->get($group['vendor']['slug']);
            $group['delivery_options'] = $vendor === null ? [] : app(ResolveDeliveryOptionsAction::class)->execute($vendor, (float) $group['subtotal']);
        }
        unset($group);

        $methods = (array) config('bookshop.checkout.methods', ['card', 'wallet']);
        if ((string) config('bookshop.bank_transfer.account_number', '') === '') {
            $methods = array_values(array_diff($methods, ['bank_transfer']));
        }

        return [
            'basket' => $basket,
            'addresses' => CustomerAddress::query()->where('user_id', $userId)->orderByDesc('is_default')->orderBy('id')->get()
                ->map(fn (CustomerAddress $a) => ['id' => $a->id, 'label' => $a->label] + $a->snapshot())->values()->all(),
            'payment_methods' => $methods,
            'wallet_balance' => app(ListWalletAction::class)->execute($userId)['balance'],
            'currency' => $basket['currency'],
            'reservation_minutes' => (int) config('bookshop.checkout.reservation_minutes', 30),
        ];
    }
}
