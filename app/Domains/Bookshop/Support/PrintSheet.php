<?php

namespace App\Domains\Bookshop\Support;

/**
 * PRINT_PRODUCTS_PLAN PP1 (PD1): the sheet sizes the desk prints on, in
 * millimetres, upright. A layout is laid out on one, and a paper is stock
 * of one (PD2 as built: A3 glossy is its own paper with its own prices).
 */
final class PrintSheet
{
    public const SIZES = [
        'a4' => [210.0, 297.0],
        'a3' => [297.0, 420.0],
        'a5' => [148.0, 210.0],
    ];

    public const DEFAULT = 'a4';

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::SIZES);
    }

    /** Width and height in mm; an unknown size reads as A4. @return array{0: float, 1: float} */
    public static function mm(?string $code): array
    {
        return self::SIZES[$code ?? self::DEFAULT] ?? self::SIZES[self::DEFAULT];
    }

    /** The proof picture's size: the sheet at 96 px to the inch, as Chrome draws a page. @return array{0: int, 1: int} */
    public static function px(?string $code): array
    {
        [$w, $h] = self::mm($code);

        return [(int) round($w / 25.4 * 96), (int) round($h / 25.4 * 96)];
    }

    /** The largest sheet: no design can be bigger. @return array{0: float, 1: float} */
    public static function largest(): array
    {
        return [max(array_column(self::SIZES, 0)), max(array_column(self::SIZES, 1))];
    }

    public static function label(?string $code): string
    {
        return strtoupper(isset(self::SIZES[$code ?? '']) ? (string) $code : self::DEFAULT);
    }
}
