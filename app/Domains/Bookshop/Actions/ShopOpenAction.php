<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Settings\Actions\SetSettingAction;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;

/**
 * The whole shop on or off (BOOKSHOP_PLAN §7 "shop on/off", slice B11).
 * Closed, the public shop pages show the office's notice instead —
 * browsing, the cart and the checkout — while a customer's own orders,
 * quotes and wishlist stay reachable, the vendor portal stays open, and
 * the office sees the shop as usual. Open by default.
 */
class ShopOpenAction
{
    public function isOpen(): bool
    {
        $value = app(SettingsRepositoryInterface::class)->get((string) config('bookshop.shop.open_setting_key'));
        if ($value === null || $value === '') {
            return true;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    public function message(): ?string
    {
        $message = trim((string) app(SettingsRepositoryInterface::class)->get((string) config('bookshop.shop.closed_message_key')));

        return $message === '' ? null : $message;
    }

    public function set(bool $open, ?string $message = null): void
    {
        $settings = app(SetSettingAction::class);
        $settings->execute((string) config('bookshop.shop.open_setting_key'), $open, 'boolean', 'bookshop', 'Bookstore: the shop is open');
        $settings->execute((string) config('bookshop.shop.closed_message_key'), trim((string) $message), 'string', 'bookshop', 'Bookstore: notice shown while closed');
    }
}
