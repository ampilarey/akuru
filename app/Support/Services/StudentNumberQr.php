<?php

namespace App\Support\Services;

class StudentNumberQr
{
    public function svg(string $payload, int $size = 120): string
    {
        $bits = $this->bits($payload);
        $cells = 21;
        $cell = $size / $cells;
        $rects = '';
        for ($y = 0; $y < $cells; $y++) {
            for ($x = 0; $x < $cells; $x++) {
                $finder = ($x < 7 && $y < 7) || ($x >= $cells - 7 && $y < 7) || ($x < 7 && $y >= $cells - 7);
                $on = $finder
                    ? $this->finderOn($x % 7, $y % 7)
                    : $bits[($y * $cells + $x) % strlen($bits)] === '1';
                if ($on) {
                    $rects .= sprintf(
                        '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="#1f1f1f"/>',
                        $x * $cell,
                        $y * $cell,
                        $cell,
                        $cell,
                    );
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" data-qr="%s" role="img" aria-label="QR %s"><rect width="%d" height="%d" fill="#fff"/>%s</svg>',
            $size,
            $size,
            $size,
            $size,
            e($payload),
            e($payload),
            $size,
            $size,
            $rects,
        );
    }

    private function bits(string $payload): string
    {
        $hash = hash('sha256', $payload);
        $bits = '';
        foreach (str_split($hash) as $char) {
            $bits .= str_pad(decbin(hexdec($char)), 4, '0', STR_PAD_LEFT);
        }

        return $bits;
    }

    private function finderOn(int $x, int $y): bool
    {
        $edge = $x === 0 || $x === 6 || $y === 0 || $y === 6;
        $center = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;

        return $edge || $center;
    }
}
