<?php

namespace App\Domains\Bookshop\Support;

/**
 * COMMERCE_PARITY_PLAN P7b: how many SMS a text is billed as. Plain Latin
 * text (the GSM 7-bit set) fits 160 characters in one message and 153 in
 * each part of a longer one; anything else — Dhivehi, Arabic, most emoji —
 * goes as Unicode, 70 and 67. Campaigns.jsx counts the same way.
 */
final class SmsSegments
{
    private const GSM = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const GSM_EXTENDED = '^{}\\[~]|€';

    public static function unicode(string $text): bool
    {
        foreach (mb_str_split($text) as $char) {
            if (! str_contains(self::GSM, $char) && ! str_contains(self::GSM_EXTENDED, $char)) {
                return true;
            }
        }

        return false;
    }

    public static function count(string $text): int
    {
        if ($text === '') {
            return 1;
        }
        if (self::unicode($text)) {
            $length = mb_strlen($text);

            return $length <= 70 ? 1 : (int) ceil($length / 67);
        }
        $length = 0;
        foreach (mb_str_split($text) as $char) {
            $length += str_contains(self::GSM_EXTENDED, $char) ? 2 : 1;
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }
}
