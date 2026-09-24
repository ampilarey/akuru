<?php

namespace App\Support\Qr;

/**
 * Reed–Solomon error correction over GF(256), as QR codes use it
 * (primitive polynomial x^8 + x^4 + x^3 + x^2 + 1, generator roots α^0…α^(n-1)).
 */
final class ReedSolomon
{
    /** @var list<int> */
    private static array $exp = [];

    /** @var list<int> */
    private static array $log = [];

    /**
     * The `$ecLength` error-correction codewords for one block of data.
     *
     * @param  list<int>  $data
     * @return list<int>
     */
    public static function remainder(array $data, int $ecLength): array
    {
        self::tables();
        $generator = self::generator($ecLength);

        $remainder = array_merge($data, array_fill(0, $ecLength, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coefficient = $remainder[$i];
            if ($coefficient === 0) {
                continue;
            }
            $logCoefficient = self::$log[$coefficient];
            for ($j = 0; $j < count($generator); $j++) {
                if ($generator[$j] !== 0) {
                    $remainder[$i + $j] ^= self::$exp[(self::$log[$generator[$j]] + $logCoefficient) % 255];
                }
            }
        }

        return array_slice($remainder, count($data));
    }

    /** @return list<int> monic generator polynomial, highest degree first */
    private static function generator(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $k => $coefficient) {
                $next[$k] ^= $coefficient;
                $next[$k + 1] ^= self::multiply($coefficient, self::$exp[$i]);
            }
            $poly = $next;
        }

        return $poly;
    }

    private static function multiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    private static function tables(): void
    {
        if (self::$exp !== []) {
            return;
        }

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        self::$exp[255] = self::$exp[0];
    }
}
