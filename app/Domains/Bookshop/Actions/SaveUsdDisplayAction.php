<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Settings\Actions\SetSettingAction;

/** The office turns dollar prices on or off and sets the rate (slice B9f). */
class SaveUsdDisplayAction
{
    public function execute(bool $on, float $rate): void
    {
        $settings = app(SetSettingAction::class);
        $settings->execute((string) config('bookshop.usd.display_setting_key'), $on, 'boolean', 'bookshop', 'Bookstore: show prices in USD as a guide');
        $settings->execute((string) config('bookshop.usd.rate_setting_key'), round($rate, 4), 'string', 'bookshop', 'Bookstore: MVR for one US dollar');
    }
}
