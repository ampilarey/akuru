<?php

use App\Support\Qr\QrMatrix;
use App\Support\Services\StudentNumberQr;

/**
 * The certificate and ID-card QR used to be corner squares around hashed
 * bits, which no decoder can read (KNOWN_ISSUES, STATUS §5gi). These pin the
 * replacement to an independent encoder, module for module.
 *
 * The reference matrices come from the npm `qrcode` package with the same
 * payload, level M, byte mode and a forced mask. Together they cover version
 * 1, a single-block version, version information (7 and 8) and two block
 * groups (8). While building this, 64 such comparisons across versions 1–10
 * and all eight masks matched, and `jsQR` decoded every automatically masked
 * code; the fixture keeps a representative set so CI needs no Node.
 */
it('draws exactly the matrix an independent encoder draws', function () {
    $reference = require base_path('tests/Fixtures/qr_reference_matrices.php');

    foreach ($reference as $key => $expected) {
        [$payload, $mask] = explode('|', $key);
        $qr = QrMatrix::encode($payload, (int) $mask);

        expect($qr->version)->toBe($expected['version'], "{$key}: version")
            ->and($qr->rows())->toBe($expected['rows'], "{$key}: modules");
    }
});

it('chooses the smallest version that holds the payload', function () {
    expect(QrMatrix::encode('STU-2002')->version)->toBe(1)
        ->and(QrMatrix::encode('https://akuru.edu.mv/verify/ABC123')->version)->toBe(3)
        ->and(QrMatrix::encode(str_repeat('x', 213))->version)->toBe(10);
});

it('refuses a payload no version 1–10 code can hold, rather than truncating it', function () {
    expect(fn () => QrMatrix::encode(str_repeat('x', 214)))->toThrow(InvalidArgumentException::class);
});

it('prints the code as one path with a quiet zone, naming what it holds', function () {
    $payload = 'https://akuru.edu.mv/verify/ABC123';
    $svg = app(StudentNumberQr::class)->svg($payload, 150);
    $qr = QrMatrix::encode($payload);

    $dark = 0;
    foreach ($qr->rows() as $row) {
        $dark += substr_count($row, '1');
    }

    // 29 modules at version 3, plus four light modules on every side.
    expect($svg)->toContain('viewBox="0 0 37 37"')
        ->and($svg)->toContain('width="150" height="150"')
        ->and($svg)->toContain('data-qr="https://akuru.edu.mv/verify/ABC123"')
        ->and(substr_count($svg, 'h1v1h-1z'))->toBe($dark)
        // The top-left finder's corner module sits just inside the quiet zone.
        ->and($svg)->toContain('M4 4h1v1h-1z');
});
