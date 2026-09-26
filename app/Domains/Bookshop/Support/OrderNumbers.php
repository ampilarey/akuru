<?php

namespace App\Domains\Bookshop\Support;

use Illuminate\Support\Facades\DB;

/**
 * `AK-YYYY-NNNNNN` per checkout, sequential per year and never reused;
 * `-XXX` (the vendor's code) per order (plan audit finding 16). Must be
 * called inside a transaction: the year's row is locked for the read.
 */
final class OrderNumbers
{
    public static function nextCheckoutNumber(): string
    {
        $year = (int) now()->format('Y');

        DB::table('bookshop_sequences')->insertOrIgnore(['year' => $year, 'next' => 1]);
        $row = DB::table('bookshop_sequences')->where('year', $year)->lockForUpdate()->first();
        $n = (int) ($row->next ?? 1);
        DB::table('bookshop_sequences')->where('year', $year)->update(['next' => $n + 1]);

        return sprintf('AK-%d-%06d', $year, $n);
    }

    public static function orderNumber(string $checkoutNumber, string $vendorCode): string
    {
        return $checkoutNumber.'-'.strtoupper($vendorCode);
    }
}
