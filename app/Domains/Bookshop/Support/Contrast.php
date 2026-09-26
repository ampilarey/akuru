<?php

namespace App\Domains\Bookshop\Support;

/**
 * WCAG 2 contrast (plan §6.2: "a colour pair that fails readability is
 * refused, with the reason"). Relative luminance from sRGB, ratio between
 * 1 and 21; 4.5 is the floor for body text, 3 for large text and
 * interface parts like links and borders.
 */
final class Contrast
{
    public const TEXT = 4.5;

    public const LARGE = 3.0;

    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        [$hi, $lo] = $la >= $lb ? [$la, $lb] : [$lb, $la];

        return round(($hi + 0.05) / ($lo + 0.05), 2);
    }

    /** `#RRGGBB` or `#RGB`, any case; null for anything else. */
    public static function normalize(?string $hex): ?string
    {
        $hex = strtoupper(trim((string) $hex));
        if (preg_match('/^#?([0-9A-F]{6})$/', $hex, $m)) {
            return '#'.$m[1];
        }
        if (preg_match('/^#?([0-9A-F]{3})$/', $hex, $m)) {
            return '#'.$m[1][0].$m[1][0].$m[1][1].$m[1][1].$m[1][2].$m[1][2];
        }

        return null;
    }

    public static function isDark(string $hex): bool
    {
        return self::luminance($hex) < 0.18;
    }

    /** Mixed toward white by `$amount` (0–1). */
    public static function lighten(string $hex, float $amount): string
    {
        $hex = self::normalize($hex) ?? '#000000';
        $out = '#';
        foreach ([1, 3, 5] as $offset) {
            $c = hexdec(substr($hex, $offset, 2));
            $out .= str_pad(strtoupper(dechex((int) round($c + (255 - $c) * $amount))), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    private static function luminance(string $hex): float
    {
        $hex = self::normalize($hex) ?? '#000000';
        $channels = [];
        foreach ([1, 3, 5] as $offset) {
            $c = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
