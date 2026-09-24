<?php

namespace App\Support\Services;

use App\Support\Qr\QrMatrix;

/**
 * The QR code printed on certificates (*scan to verify*) and student ID
 * cards, as an inline SVG.
 *
 * **Until 2026-09-25 this was not a QR code.** It drew the three corner
 * squares and filled the rest with bits of a SHA-256 hash of the payload,
 * so it looked right and no decoder could read it: no certificate had ever
 * been verifiable by scanning (KNOWN_ISSUES, found by the gate card slice).
 * It now draws a real one from {@see QrMatrix}, with the four-module quiet
 * zone the standard asks for, as a single path so a printed certificate
 * stays small.
 */
class StudentNumberQr
{
    private const QUIET_ZONE = 4;

    public function svg(string $payload, int $size = 120): string
    {
        $qr = QrMatrix::encode($payload);
        $modules = $qr->size();
        $extent = $modules + 2 * self::QUIET_ZONE;

        $path = '';
        for ($row = 0; $row < $modules; $row++) {
            for ($col = 0; $col < $modules; $col++) {
                if ($qr->isDark($row, $col)) {
                    $path .= sprintf('M%d %dh1v1h-1z', $col + self::QUIET_ZONE, $row + self::QUIET_ZONE);
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges" data-qr="%s" role="img" aria-label="QR %s"><rect width="%d" height="%d" fill="#fff"/><path d="%s" fill="#1f1f1f"/></svg>',
            $size,
            $size,
            $extent,
            $extent,
            e($payload),
            e($payload),
            $extent,
            $extent,
            $path,
        );
    }
}
