<?php

use App\Support\Services\GhostscriptPdfRasterizer;

/**
 * A shared host had Ghostscript 9.27 (2026-10-12). `--permit-file-read`
 * arrived in 9.50, and an older Ghostscript fails on it, so a PDF upload's
 * page count failed there. The switch is passed only where it exists
 * (STATUS §5ry).
 */
it('asks for read permission only from Ghostscript 9.50 on', function () {
    expect(GhostscriptPdfRasterizer::readPermission('9.27', '/tmp/a.pdf'))->toBe([])
        ->and(GhostscriptPdfRasterizer::readPermission('9.26', '/tmp/a.pdf'))->toBe([])
        ->and(GhostscriptPdfRasterizer::readPermission('9.50', '/tmp/a.pdf'))->toBe(['--permit-file-read=/tmp/a.pdf'])
        ->and(GhostscriptPdfRasterizer::readPermission('9.56.1', '/tmp/a.pdf'))->toBe(['--permit-file-read=/tmp/a.pdf'])
        ->and(GhostscriptPdfRasterizer::readPermission('10.02.1', '/tmp/a.pdf'))->toBe(['--permit-file-read=/tmp/a.pdf']);
});

it('counts and draws the pages of a PDF through the real Ghostscript', function () {
    $gs = trim((string) shell_exec('command -v gs'));
    $rasterizer = new GhostscriptPdfRasterizer($gs);
    $pdf = (string) shell_exec(escapeshellarg($gs).' -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sOutputFile=- -c showpage showpage 2>/dev/null');

    expect($rasterizer->pages($pdf))->toBe(2)
        ->and(substr($rasterizer->toPng($pdf, 2, 30), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
})->skip(fn () => trim((string) shell_exec('command -v gs')) === '', 'no Ghostscript on this machine');
