<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\PrintLayout;

/**
 * NAME_TAGS_PLAN §3.4, §5.5, §5.6 (NT5): a customer's own artwork — what is
 * read from the file, and the preflight worked out from that and the job's
 * options (§5.5): resolution at the printed size (warn under 300 dpi, refuse
 * under 150), a sheet against A4's proportion (±2%), colour on a
 * black-and-white job and transparency (warnings), a PDF's pages against the
 * sheets ordered. GD and the file's own bytes, no SDK (rule 4).
 */
final class PrintUpload
{
    public const KINDS = ['tag', 'sheet'];

    public const WARN_DPI = 300;

    public const REFUSE_DPI = 150;

    /** A4 portrait, width over height, and how far a sheet upload may be from it. */
    public const A4_RATIO = 210 / 297;

    public const A4_TOLERANCE = 0.02;

    /** Images larger than this are not sampled for colour (GD would need too much memory); colour is then unknown. */
    public const SAMPLE_MAX_PIXELS = 25_000_000;

    /**
     * What the file is, from its bytes: null when it is not a PNG, a JPEG or
     * a PDF this host can read.
     *
     * @return array{type: string, w: ?int, h: ?int, pages: int, alpha: bool, colour: ?bool}|null
     */
    public static function inspect(string $bytes): ?array
    {
        if (str_starts_with($bytes, '%PDF-')) {
            return ['type' => 'pdf', 'w' => null, 'h' => null, 'pages' => 0, 'alpha' => false, 'colour' => null];
        }
        $size = @getimagesizefromstring($bytes);
        if ($size === false || ! in_array($size[2] ?? null, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            return null;
        }
        [$w, $h] = $size;
        $png = $size[2] === IMAGETYPE_PNG;

        return [
            'type' => $png ? 'png' : 'jpeg',
            'w' => (int) $w,
            'h' => (int) $h,
            'pages' => 1,
            'alpha' => $png && self::pngHasAlpha($bytes),
            'colour' => $w * $h <= self::SAMPLE_MAX_PIXELS ? self::hasColour($bytes) : null,
        ];
    }

    /**
     * The preflight for this file on this job.
     *
     * @param  array<string, mixed>  $meta  from inspect()
     * @return array{dpi: ?int, warnings: list<array{key: string, vars: array<string, int|string>}>, refusals: list<array{key: string, vars: array<string, int|string>}>}
     */
    public static function preflight(array $meta, string $kind, ?PrintLayout $layout, bool $colour, int $sheets): array
    {
        $warnings = [];
        $refusals = [];
        $dpi = null;
        if (($meta['type'] ?? null) === 'pdf') {
            if ($kind !== 'sheet') {
                $refusals[] = ['key' => 'print.upload_pdf_sheet_only', 'vars' => []];
            } elseif ((int) $meta['pages'] !== $sheets) {
                $refusals[] = ['key' => 'print.upload_pdf_pages', 'vars' => ['pages' => (int) $meta['pages'], 'sheets' => $sheets]];
            }
        } else {
            [$wMm, $hMm] = $kind === 'sheet' || $layout === null ? PrintSheet::mm($layout?->sheet_size) : [(float) $layout->tag_w_mm, (float) $layout->tag_h_mm];
            $wPx = max(1, (int) $meta['w']);
            $hPx = max(1, (int) $meta['h']);
            // Fitted inside the box (contain): the side that fills it sets the resolution.
            $dpi = (int) floor(25.4 / min($wMm / $wPx, $hMm / $hPx));
            if ($dpi < self::REFUSE_DPI) {
                $refusals[] = ['key' => 'print.upload_dpi_refused', 'vars' => ['dpi' => $dpi, 'min' => self::REFUSE_DPI]];
            } elseif ($dpi < self::WARN_DPI) {
                $warnings[] = ['key' => 'print.upload_dpi_low', 'vars' => ['dpi' => $dpi, 'good' => self::WARN_DPI]];
            }
            if ($kind === 'sheet' && abs(($wPx / $hPx) / ($wMm / $hMm) - 1) > self::A4_TOLERANCE) {
                // Every sheet size has A4's shape (1:√2), so the one tolerance holds for all.
                $refusals[] = ['key' => 'print.upload_not_a4', 'vars' => ['size' => PrintSheet::label($layout?->sheet_size)]];
            }
            if (! empty($meta['alpha'])) {
                $warnings[] = ['key' => 'print.upload_transparent', 'vars' => []];
            }
        }
        if (! $colour && ($meta['colour'] ?? false) === true) {
            $warnings[] = ['key' => 'print.upload_colour_on_bw', 'vars' => []];
        }

        return ['dpi' => $dpi, 'warnings' => $warnings, 'refusals' => $refusals];
    }

    /** One element, the artwork, filling the tag — the design an upload prints with. @return array<string, mixed> */
    public static function design(float $wMm, float $hMm): array
    {
        return [
            'version' => 1,
            'tag' => ['w' => $wMm, 'h' => $hMm],
            'background' => ['color' => '#FFFFFF'],
            'elements' => [['type' => 'image', 'upload' => true, 'media' => 0, 'x' => 0, 'y' => 0, 'w' => $wMm, 'h' => $hMm, 'rotate' => 0, 'fit' => 'contain']],
        ];
    }

    /** A whole page of this sheet size as one cell — how a sheet upload is laid out. Not saved. */
    public static function sheetLayout(?string $sheetSize = null): PrintLayout
    {
        [$w, $h] = PrintSheet::mm($sheetSize);

        return new PrintLayout([
            'name' => PrintSheet::label($sheetSize).' sheet', 'sheet_size' => $sheetSize ?? PrintSheet::DEFAULT, 'columns' => 1, 'rows' => 1, 'tag_w_mm' => $w, 'tag_h_mm' => $h,
            'margin_top_mm' => 0, 'margin_left_mm' => 0, 'gutter_x_mm' => 0, 'gutter_y_mm' => 0, 'sheet_code' => 'full', 'is_active' => true,
        ]);
    }

    private static function pngHasAlpha(string $bytes): bool
    {
        // IHDR colour type: 4 grey + alpha, 6 RGBA; a tRNS chunk makes a palette or grey image transparent too.
        $type = ord($bytes[25] ?? "\0");

        return $type === 4 || $type === 6 || str_contains(substr($bytes, 0, 65536), 'tRNS');
    }

    /** Any clearly coloured pixel, on a grid of samples. */
    private static function hasColour(string $bytes): ?bool
    {
        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            return null;
        }
        try {
            $w = imagesx($img);
            $h = imagesy($img);
            $steps = 48;
            for ($i = 0; $i < $steps; $i++) {
                for ($j = 0; $j < $steps; $j++) {
                    $rgba = imagecolorsforindex($img, imagecolorat($img, (int) (($i + 0.5) * $w / $steps), (int) (($j + 0.5) * $h / $steps)));
                    if ($rgba['alpha'] > 100) {
                        continue;
                    }
                    if (max($rgba['red'], $rgba['green'], $rgba['blue']) - min($rgba['red'], $rgba['green'], $rgba['blue']) > 24) {
                        return true;
                    }
                }
            }

            return false;
        } finally {
            imagedestroy($img);
        }
    }
}
