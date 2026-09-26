<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;

/**
 * Prices in US dollars, as a guide only (BOOKSHOP_PLAN §11, slice B9f):
 * "≈ USD 5.51" beside a price when the office turned it on, at the rate
 * it set (MVR for one dollar). Nothing is ever charged in dollars.
 */
final class Usd
{
    /**
     * Read once per request (kept on the request, so a page of cards is one
     * settings read, and the next request sees a change at once).
     *
     * @return array{on: bool, rate: float}
     */
    public static function state(): array
    {
        $attributes = request()->attributes;
        if (! $attributes->has('bookshop_usd')) {
            $settings = app(SettingsRepositoryInterface::class);
            $on = in_array(strtolower(trim((string) $settings->get((string) config('bookshop.usd.display_setting_key')))), ['1', 'true', 'on', 'yes'], true);
            $rate = (float) ($settings->get((string) config('bookshop.usd.rate_setting_key')) ?: 0);
            $rate = $rate > 0 ? $rate : (float) config('bookshop.usd.default_rate');
            $attributes->set('bookshop_usd', ['on' => $on, 'rate' => $rate]);
        }

        return $attributes->get('bookshop_usd');
    }

    /** "≈ USD 5.51", or an empty string when dollars are not shown. */
    public static function line(float|string|null $mvr): string
    {
        $state = self::state();
        if (! $state['on'] || $mvr === null || $mvr === '') {
            return '';
        }

        return '≈ USD '.number_format(round((float) $mvr / $state['rate'], 2), 2);
    }
}
