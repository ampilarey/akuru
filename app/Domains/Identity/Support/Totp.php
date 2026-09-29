<?php

namespace App\Domains\Identity\Support;

/**
 * Time-based one-time passwords (RFC 6238), the codes an authenticator app
 * shows (STATUS §5lk): HMAC-SHA1 over the 30-second step, six digits. No
 * package — the algorithm is short and fixed, and nothing leaves the server.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const PERIOD = 30;

    /** A new secret: 160 random bits, base32 as the apps expect. */
    public static function secret(): string
    {
        return self::base32(random_bytes(20));
    }

    /** The current 30-second step. */
    public static function step(?int $time = null): int
    {
        return intdiv($time ?? time(), self::PERIOD);
    }

    public static function code(string $secret, int $step): string
    {
        $key = self::unbase32($secret);
        $hash = hash_hmac('sha1', pack('N*', 0, $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * The step the code belongs to — this one, or one either side for a clock
     * a little off — or null. The caller refuses a step it has already used.
     */
    public static function matchStep(string $secret, string $code, ?int $time = null): ?int
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return null;
        }
        $now = self::step($time);
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** What the app scans: otpauth://totp/Akuru:alice@example.com?secret=…&issuer=Akuru */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return 'otpauth://totp/'.$label.'?'.http_build_query(['secret' => $secret, 'issuer' => $issuer, 'digits' => 6, 'period' => self::PERIOD], '', '&', PHP_QUERY_RFC3986);
    }

    private static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function unbase32(string $text): string
    {
        $text = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $text) ?? '');
        $bits = '';
        foreach (str_split($text) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }

        return $out;
    }
}
