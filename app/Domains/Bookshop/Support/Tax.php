<?php

namespace App\Domains\Bookshop\Support;

/**
 * Decision 4: prices include tax; the tax inside a price is
 * `price − price / (1 + rate)`, at the rate for the product's class from
 * the office settings. Shown on a receipt only for a GST-registered vendor.
 */
final class Tax
{
    public static function rate(string $taxClass): float
    {
        $rates = (array) config('bookshop.tax.rates', []);

        return (float) ($rates[$taxClass] ?? 0);
    }

    /** The tax inside a tax-inclusive amount, rounded to laari. */
    public static function inclusive(float $amount, string $taxClass): float
    {
        $rate = self::rate($taxClass);
        if ($rate <= 0 || $amount <= 0) {
            return 0.0;
        }

        return round($amount - $amount / (1 + $rate / 100), 2);
    }
}
