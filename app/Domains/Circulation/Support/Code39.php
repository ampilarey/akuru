<?php

namespace App\Domains\Circulation\Support;

/**
 * Code 39 barcodes as inline SVG.
 *
 * **Chosen over QR deliberately.** The plan says "QR or barcode label", and at
 * a circulation desk the reader is a cheap USB wedge scanner, not a camera —
 * every one of them reads Code 39 out of the box, and it types the accession
 * number straight into the focused field. QR would mean adding a dependency
 * to draw it and a camera to read it, for a worse desk workflow.
 *
 * Pure PHP, no package (rule 4 keeps SDKs out of domain logic; here there is
 * simply no SDK at all). Code 39 needs no checksum and encodes A–Z, 0–9, and
 * `-. $/+%`, which covers every accession number this domain allocates.
 */
final class Code39
{
    /** Each glyph is 9 elements, wide/narrow, bar-first. 1 = wide. */
    private const PATTERNS = [
        '0' => '000110100', '1' => '100100001', '2' => '001100001', '3' => '101100000',
        '4' => '000110001', '5' => '100110000', '6' => '001110000', '7' => '000100101',
        '8' => '100100100', '9' => '001100100', 'A' => '100001001', 'B' => '001001001',
        'C' => '101001000', 'D' => '000011001', 'E' => '100011000', 'F' => '001011000',
        'G' => '000001101', 'H' => '100001100', 'I' => '001001100', 'J' => '000011100',
        'K' => '100000011', 'L' => '001000011', 'M' => '101000010', 'N' => '000010011',
        'O' => '100010010', 'P' => '001010010', 'Q' => '000000111', 'R' => '100000110',
        'S' => '001000110', 'T' => '000010110', 'U' => '110000001', 'V' => '011000001',
        'W' => '111000000', 'X' => '010010001', 'Y' => '110010000', 'Z' => '011010000',
        '-' => '010000101', '.' => '110000100', ' ' => '011000100', '$' => '010101000',
        '/' => '010100010', '+' => '010001010', '%' => '000101010', '*' => '010010100',
    ];

    /**
     * @param  string  $value  encoded as-is; unsupported characters are dropped
     * @param  int  $narrow  width of a narrow element, in user units
     */
    public static function svg(string $value, int $height = 40, int $narrow = 2): string
    {
        // '*' is the start/stop sentinel, so it may not appear in the payload.
        $chars = array_values(array_filter(
            str_split(strtoupper(trim($value))),
            fn (string $c): bool => isset(self::PATTERNS[$c]) && $c !== '*',
        ));

        // The readable text must be what the bars actually encode. A label
        // whose printed number differs from its barcode is worse than no
        // label: the scanner and the human disagree and nobody notices.
        $encoded = implode('', $chars);

        $sequence = array_merge(['*'], $chars, ['*']);

        $bars = [];
        $x = 0;

        foreach ($sequence as $i => $char) {
            $pattern = self::PATTERNS[$char];

            for ($e = 0; $e < 9; $e++) {
                $width = $pattern[$e] === '1' ? $narrow * 3 : $narrow;

                // Even indexes are bars, odd are spaces.
                if ($e % 2 === 0) {
                    $bars[] = ['x' => $x, 'w' => $width];
                }

                $x += $width;
            }

            // One narrow space between characters, but not after the last.
            if ($i !== count($sequence) - 1) {
                $x += $narrow;
            }
        }

        $rects = implode('', array_map(
            fn (array $bar): string => '<rect x="'.$bar['x'].'" y="0" width="'.$bar['w'].'" height="'.$height.'" fill="#000"/>',
            $bars,
        ));

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$x.'" height="'.$height.'" '
            .'viewBox="0 0 '.$x.' '.$height.'" role="img" aria-label="'.htmlspecialchars($encoded, ENT_QUOTES).'">'
            .$rects.'</svg>';
    }
}
