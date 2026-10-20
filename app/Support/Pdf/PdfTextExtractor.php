<?php

namespace App\Support\Pdf;

use App\Domains\Library\Contracts\PdfPageTextExtractor;

/**
 * Plain text, one string per page, out of a PDF's content streams.
 *
 * The interpreter runs the text operators of ISO 32000-1 §9 — `BT`/`ET`,
 * `Tf`, `Td`/`TD`/`Tm`/`T*`, `Tj`/`TJ`/`'`/`"`, the spacing operators — over
 * the graphics state (`q`/`Q`/`cm`), descends into form XObjects, and keeps
 * every shown run of glyphs with its position on the page. Runs are then
 * gathered into lines by baseline, ordered along the line (right-to-left
 * when the line is mostly Arabic or Thaana), spaced by the gaps between
 * them, and grouped into paragraphs by the vertical gaps between lines.
 *
 * Since BACKLOG B2 (STATUS §5tr) a page set in two columns is read column
 * by column — the left first, or the right on a mostly right-to-left page —
 * with a title that spans both kept above them; and an Arabic word a
 * producer wrote in visual order (its letters' shapes show it: a final form
 * first, an initial form last) is put back in reading order.
 *
 * What it does not do: rasterise (a scanned book is pictures, and yields no
 * text; OCR is `RecogniseLibraryItemPagesAction`), read three or more
 * columns (a table stays as it was), or undo visual order in Thaana, whose
 * letters have no positional shapes to tell by.
 */
final class PdfTextExtractor implements PdfPageTextExtractor
{
    private const RTL = '/[\x{0590}-\x{05FF}\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{0780}-\x{07BF}\x{08A0}-\x{08FF}\x{FB1D}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    private const LTR = '/[A-Za-z\x{00C0}-\x{024F}\x{0370}-\x{03FF}\x{0400}-\x{04FF}]/u';

    /** @return list<string> */
    public function pages(string $pdf): array
    {
        $document = PdfDocument::parse($pdf);
        $texts = [];
        foreach ($document->pages() as $page) {
            try {
                $texts[] = $this->pageText($document, $page['dict'], $page['resources']);
            } catch (\Throwable) {
                $texts[] = '';
            }
        }

        return $texts;
    }

    private function pageText(PdfDocument $document, PdfDict $page, ?PdfDict $resources): string
    {
        $contents = $document->resolve($page->get('Contents'));
        $streams = is_array($contents) ? $contents : [$contents];
        $content = '';
        foreach ($streams as $stream) {
            $stream = $document->resolve($stream);
            if ($stream instanceof PdfStream) {
                $content .= ($stream->decoded() ?? '')."\n";
            }
        }
        if (trim($content) === '') {
            return '';
        }

        $runs = [];
        $fonts = [];
        $this->interpret($document, $content, $resources, [1, 0, 0, 1, 0, 0], $runs, $fonts, 0);

        return $this->assemble($runs);
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}  $ctm
     * @param  list<array{text: string, x0: float, x1: float, y: float, size: float}>  $runs
     * @param  array<string, PdfFont>  $fonts
     */
    private function interpret(PdfDocument $document, string $content, ?PdfDict $resources, array $ctm, array &$runs, array &$fonts, int $depth): void
    {
        if ($depth > 12 || count($runs) > 200000) {
            return;
        }

        $lexer = new PdfLexer($content);
        $operands = [];
        $stack = [];

        $font = null;
        $size = 0.0;
        $charSpacing = 0.0;
        $wordSpacing = 0.0;
        $hscale = 1.0;
        $leading = 0.0;
        $rise = 0.0;
        $tm = [1, 0, 0, 1, 0, 0];
        $tlm = $tm;

        $fontResources = $document->entry($resources, 'Font');
        $xobjects = $document->entry($resources, 'XObject');

        $show = function (string $bytes) use (&$font, &$size, &$charSpacing, &$wordSpacing, &$hscale, &$rise, &$tm, &$ctm, &$runs): void {
            $current = $font ?? PdfFont::fallback();
            $trm = self::multiply([$size * $hscale, 0, 0, $size, 0, $rise], self::multiply($tm, $ctm));
            $start = [$trm[4], $trm[5]];
            $deviceSize = hypot($trm[2], $trm[3]);
            $text = '';
            foreach ($current->decode($bytes) as $glyph) {
                $text .= $glyph['text'];
                $advance = ($glyph['width'] / 1000 * $size + $charSpacing + ($glyph['space'] ? $wordSpacing : 0)) * $hscale;
                $tm = self::multiply([1, 0, 0, 1, $advance, 0], $tm);
            }
            $end = self::multiply($tm, $ctm);
            if ($text !== '' && trim($text) !== '' || $text === ' ') {
                $runs[] = [
                    'text' => $text,
                    'x0' => $start[0],
                    'x1' => $end[4],
                    'y' => $start[1],
                    'size' => $deviceSize > 0 ? $deviceSize : abs($size),
                ];
            }
        };

        while (true) {
            $token = PdfParser::parse($lexer, false);
            if ($token === null && $lexer->pos >= $lexer->end()) {
                break;
            }
            if (! $token instanceof PdfKeyword) {
                $operands[] = $token;
                if (count($operands) > 32) {
                    array_shift($operands);
                }

                continue;
            }

            $op = $token->value;
            $n = fn (int $index) => is_numeric($operands[$index] ?? null) ? (float) $operands[$index] : 0.0;

            switch ($op) {
                case 'q':
                    $stack[] = [$ctm, $font, $size, $charSpacing, $wordSpacing, $hscale, $leading, $rise];
                    if (count($stack) > 256) {
                        array_shift($stack);
                    }
                    break;
                case 'Q':
                    if ($stack !== []) {
                        [$ctm, $font, $size, $charSpacing, $wordSpacing, $hscale, $leading, $rise] = array_pop($stack);
                    }
                    break;
                case 'cm':
                    if (count($operands) >= 6) {
                        $ctm = self::multiply([$n(0), $n(1), $n(2), $n(3), $n(4), $n(5)], $ctm);
                    }
                    break;
                case 'BT':
                    $tm = [1, 0, 0, 1, 0, 0];
                    $tlm = $tm;
                    break;
                case 'ET':
                    break;
                case 'Tf':
                    $size = $n(1);
                    $name = $operands[0] ?? null;
                    $key = $name instanceof PdfName ? $name->value : '';
                    if (! isset($fonts[$key])) {
                        $dict = $document->entry($fontResources, $key);
                        $fonts[$key] = $dict instanceof PdfDict ? PdfFont::fromDict($dict, $document) : PdfFont::fallback();
                    }
                    $font = $fonts[$key];
                    break;
                case 'Td':
                    $tlm = self::multiply([1, 0, 0, 1, $n(0), $n(1)], $tlm);
                    $tm = $tlm;
                    break;
                case 'TD':
                    $leading = -$n(1);
                    $tlm = self::multiply([1, 0, 0, 1, $n(0), $n(1)], $tlm);
                    $tm = $tlm;
                    break;
                case 'Tm':
                    if (count($operands) >= 6) {
                        $tlm = [$n(0), $n(1), $n(2), $n(3), $n(4), $n(5)];
                        $tm = $tlm;
                    }
                    break;
                case 'T*':
                    $tlm = self::multiply([1, 0, 0, 1, 0, -$leading], $tlm);
                    $tm = $tlm;
                    break;
                case 'TL':
                    $leading = $n(0);
                    break;
                case 'Tc':
                    $charSpacing = $n(0);
                    break;
                case 'Tw':
                    $wordSpacing = $n(0);
                    break;
                case 'Tz':
                    $hscale = $n(0) / 100;
                    break;
                case 'Ts':
                    $rise = $n(0);
                    break;
                case 'Tj':
                    if (is_string($operands[0] ?? null)) {
                        $show($operands[0]);
                    }
                    break;
                case "'":
                    $tlm = self::multiply([1, 0, 0, 1, 0, -$leading], $tlm);
                    $tm = $tlm;
                    if (is_string($operands[0] ?? null)) {
                        $show($operands[0]);
                    }
                    break;
                case '"':
                    $wordSpacing = $n(0);
                    $charSpacing = $n(1);
                    $tlm = self::multiply([1, 0, 0, 1, 0, -$leading], $tlm);
                    $tm = $tlm;
                    if (is_string($operands[2] ?? null)) {
                        $show($operands[2]);
                    }
                    break;
                case 'TJ':
                    $array = $operands[0] ?? null;
                    if (is_array($array)) {
                        foreach ($array as $element) {
                            if (is_string($element)) {
                                $show($element);
                            } elseif (is_numeric($element)) {
                                $tm = self::multiply([1, 0, 0, 1, -(float) $element / 1000 * $size * $hscale, 0], $tm);
                            }
                        }
                    }
                    break;
                case 'Do':
                    $name = $operands[0] ?? null;
                    $xobject = $name instanceof PdfName ? $document->entry($xobjects, $name->value) : null;
                    if ($xobject instanceof PdfStream && $xobject->dict->name('Subtype') === 'Form') {
                        $inner = $xobject->decoded();
                        if ($inner !== null && $inner !== '') {
                            $matrix = $document->resolve($xobject->dict->get('Matrix'));
                            $formCtm = is_array($matrix) && count($matrix) === 6
                                ? self::multiply(array_map(fn ($v) => is_numeric($v) ? (float) $v : 0.0, array_values($matrix)), $ctm)
                                : $ctm;
                            $formResources = $document->resolve($xobject->dict->get('Resources'));
                            $innerFonts = [];
                            $this->interpret($document, $inner, $formResources instanceof PdfDict ? $formResources : $resources, $formCtm, $runs, $innerFonts, $depth + 1);
                        }
                    }
                    break;
                case 'BI':
                    // Inline image: operands up to ID, then binary up to EI.
                    while (true) {
                        $inner = PdfParser::parse($lexer, false);
                        if ($inner === null && $lexer->pos >= $lexer->end()) {
                            break 2;
                        }
                        if ($inner instanceof PdfKeyword && $inner->value === 'ID') {
                            $lexer->skipInlineImageData();
                            break;
                        }
                    }
                    break;
                default:
                    break;
            }
            $operands = [];
        }
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}  $a
     * @param  array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}  $b
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
     */
    private static function multiply(array $a, array $b): array
    {
        return [
            $a[0] * $b[0] + $a[1] * $b[2],
            $a[0] * $b[1] + $a[1] * $b[3],
            $a[2] * $b[0] + $a[3] * $b[2],
            $a[2] * $b[1] + $a[3] * $b[3],
            $a[4] * $b[0] + $a[5] * $b[2] + $b[4],
            $a[4] * $b[1] + $a[5] * $b[3] + $b[5],
        ];
    }

    /**
     * Runs → lines → paragraphs → text.
     *
     * @param  list<array{text: string, x0: float, x1: float, y: float, size: float}>  $runs
     */
    private function assemble(array $runs): string
    {
        if ($runs === []) {
            return '';
        }
        $runs = array_map(fn (array $run) => ['text' => self::unreverseArabic($run['text'])] + $run, $runs);

        // B2: two columns are read one after the other, a spanning title above them.
        $columns = self::columns($runs);
        if ($columns !== null) {
            return trim(implode("\n\n", array_filter(array_map(fn (array $block) => $this->assembleBlock($block), $columns), fn (string $text) => $text !== '')));
        }

        return $this->assembleBlock($runs);
    }

    /**
     * BACKLOG B2: a page in two columns, as blocks in reading order — what
     * spans the gutter above the columns, the columns (right first on a
     * mostly right-to-left page), what spans it below — or null where the
     * page is not two columns. A gutter must be clear of every run but the
     * spanning ones, sit in the middle of the text, be at least one and a
     * half font sizes wide, be the only such gap (three columns is a table),
     * and have three lines or more on each side.
     *
     * @param  list<array{text: string, x0: float, x1: float, y: float, size: float}>  $runs
     * @return list<list<array{text: string, x0: float, x1: float, y: float, size: float}>>|null
     */
    public static function columns(array $runs): ?array
    {
        if (count($runs) < 6) {
            return null;
        }
        $left = min(array_column($runs, 'x0'));
        $right = max(array_column($runs, 'x1'));
        $width = $right - $left;
        if ($width <= 0) {
            return null;
        }
        $sizes = array_column($runs, 'size');
        sort($sizes);
        $size = max(1.0, (float) $sizes[intdiv(count($sizes), 2)]);

        // Runs narrower than half the text block mark where text is; wider ones may span.
        $narrow = array_values(array_filter($runs, fn (array $r) => ($r['x1'] - $r['x0']) < 0.5 * $width));
        $spans = [];
        foreach ($narrow as $r) {
            $spans[] = [$r['x0'], $r['x1']];
        }
        usort($spans, fn ($a, $b) => $a[0] <=> $b[0]);
        $gaps = [];
        $reach = $left;
        foreach ($spans as [$x0, $x1]) {
            if ($x0 - $reach >= 1.5 * $size) {
                $gaps[] = [$reach, $x0];
            }
            $reach = max($reach, $x1);
        }
        $middle = array_values(array_filter($gaps, function (array $g) use ($left, $width) {
            $centre = (($g[0] + $g[1]) / 2 - $left) / $width;

            return $centre > 0.3 && $centre < 0.7;
        }));
        if (count($gaps) !== 1 || count($middle) !== 1) {
            return null;
        }
        [$g0, $g1] = $middle[0];

        $first = $second = $spanning = [];
        foreach ($runs as $r) {
            if ($r['x1'] <= $g0 + 0.01) {
                $first[] = $r;
            } elseif ($r['x0'] >= $g1 - 0.01) {
                $second[] = $r;
            } else {
                $spanning[] = $r;
            }
        }
        $lines = fn (array $side) => count(array_unique(array_map(fn ($r) => (int) round($r['y']), $side)));
        if ($lines($first) < 3 || $lines($second) < 3) {
            return null;
        }
        $joined = implode('', array_column($runs, 'text'));
        if (preg_match_all(self::RTL, $joined) > preg_match_all(self::LTR, $joined)) {
            [$first, $second] = [$second, $first];
        }
        $top = max(max(array_column($first, 'y')), max(array_column($second, 'y')));
        $above = array_values(array_filter($spanning, fn ($r) => $r['y'] >= $top - 0.01));
        $below = array_values(array_filter($spanning, fn ($r) => $r['y'] < $top - 0.01));

        return array_values(array_filter([$above, $first, $second, $below], fn (array $block) => $block !== []));
    }

    /**
     * BACKLOG B2: an Arabic word written in visual order — its letters'
     * presentation forms read backwards, a final or isolated-final shape
     * first and an initial shape last — put back in reading order. A word
     * in logical order, or with no presentation forms, is left alone.
     */
    public static function unreverseArabic(string $text): string
    {
        if (preg_match('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text) !== 1) {
            return $text;
        }

        return (string) preg_replace_callback('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}\x{0600}-\x{06FF}]{2,}/u', function (array $m) {
            $letters = (array) preg_split('//u', $m[0], -1, PREG_SPLIT_NO_EMPTY);
            $form = fn (string $c) => self::arabicForm($c);
            $firstForm = $form($letters[0]);
            $lastForm = $form($letters[count($letters) - 1]);
            $backwards = in_array($firstForm, ['final', 'medial'], true) || in_array($lastForm, ['initial', 'medial'], true);
            $forwards = in_array($firstForm, ['initial', 'isolated'], true) && in_array($lastForm, ['final', 'isolated'], true);

            return $backwards && ! $forwards ? implode('', array_reverse($letters)) : $m[0];
        }, $text);
    }

    /**
     * The positional shape of an Arabic presentation-form letter (Unicode's
     * Arabic Presentation Forms-B), or null for anything else. Each letter's
     * forms run isolated, final, then — for a letter that joins on both
     * sides — initial and medial; the letters that join only on the right
     * (alef, dal, reh, waw and their kin, teh marbuta, the lam-alefs) have
     * the first two only, so the table names where each letter starts.
     */
    private static function arabicForm(string $char): ?string
    {
        $code = mb_ord($char, 'UTF-8');
        if ($code === false || $code < 0xFE81 || $code > 0xFEFC) {
            return null;
        }
        // Letter start => how many forms it has, in Unicode's order.
        static $letters = [
            0xFE81 => 2, 0xFE83 => 2, 0xFE85 => 2, 0xFE87 => 2, 0xFE89 => 4, 0xFE8D => 2, 0xFE8F => 4, 0xFE93 => 2,
            0xFE95 => 4, 0xFE99 => 4, 0xFE9D => 4, 0xFEA1 => 4, 0xFEA5 => 4, 0xFEA9 => 2, 0xFEAB => 2, 0xFEAD => 2,
            0xFEAF => 2, 0xFEB1 => 4, 0xFEB5 => 4, 0xFEB9 => 4, 0xFEBD => 4, 0xFEC1 => 4, 0xFEC5 => 4, 0xFEC9 => 4,
            0xFECD => 4, 0xFED1 => 4, 0xFED5 => 4, 0xFED9 => 4, 0xFEDD => 4, 0xFEE1 => 4, 0xFEE5 => 4, 0xFEE9 => 4,
            0xFEED => 2, 0xFEEF => 2, 0xFEF1 => 4, 0xFEF5 => 2, 0xFEF7 => 2, 0xFEF9 => 2, 0xFEFB => 2,
        ];
        foreach ($letters as $first => $count) {
            if ($code >= $first && $code < $first + $count) {
                return ['isolated', 'final', 'initial', 'medial'][$code - $first];
            }
        }

        return null;
    }

    /**
     * Runs → lines → paragraphs → text, for one block of the page.
     *
     * @param  list<array{text: string, x0: float, x1: float, y: float, size: float}>  $runs
     */
    private function assembleBlock(array $runs): string
    {
        if ($runs === []) {
            return '';
        }

        // Lines: runs whose baselines sit within half a font size of each other.
        usort($runs, fn ($a, $b) => $b['y'] <=> $a['y']);
        $lines = [];
        foreach ($runs as $run) {
            $last = $lines === [] ? null : $lines[count($lines) - 1];
            if ($last !== null && abs($last['y'] - $run['y']) <= 0.5 * max($last['size'], $run['size'], 1)) {
                $lines[count($lines) - 1]['runs'][] = $run;
                $lines[count($lines) - 1]['size'] = max($last['size'], $run['size']);

                continue;
            }
            $lines[] = ['y' => $run['y'], 'size' => $run['size'], 'runs' => [$run]];
        }

        $paragraphs = [];
        $current = [];
        $previous = null;
        foreach ($lines as $line) {
            $text = $this->lineText($line['runs'], $line['size']);
            if ($text === '') {
                continue;
            }
            if ($previous !== null) {
                $gap = $previous['y'] - $line['y'];
                $sizeChanged = abs($previous['size'] - $line['size']) > 0.2 * max($previous['size'], $line['size'], 1);
                if ($gap > 1.7 * max($previous['size'], $line['size'], 1) || $sizeChanged) {
                    $paragraphs[] = $current;
                    $current = [];
                }
            }
            $current[] = $text;
            $previous = $line;
        }
        if ($current !== []) {
            $paragraphs[] = $current;
        }

        $out = [];
        foreach ($paragraphs as $paragraphLines) {
            $paragraph = '';
            foreach ($paragraphLines as $lineText) {
                if ($paragraph === '') {
                    $paragraph = $lineText;
                } elseif (preg_match('/\p{L}-$/u', $paragraph) === 1 && preg_match('/^\p{Ll}/u', $lineText) === 1) {
                    $paragraph = substr($paragraph, 0, -1).$lineText;
                } else {
                    $paragraph .= ' '.$lineText;
                }
            }
            $paragraph = trim((string) preg_replace('/[ \t]+/', ' ', $paragraph));
            if ($paragraph !== '') {
                $out[] = $paragraph;
            }
        }

        $text = implode("\n\n", $out);
        if (class_exists(\Normalizer::class)) {
            // Presentation forms (Arabic ligatures, ﬁ) become their letters.
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        }

        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text));
    }

    /** @param  list<array{text: string, x0: float, x1: float, y: float, size: float}>  $runs */
    private function lineText(array $runs, float $size): string
    {
        $joined = implode('', array_column($runs, 'text'));
        $rtl = preg_match_all(self::RTL, $joined) > preg_match_all(self::LTR, $joined);

        $runs = $this->attachCombiningMarks(array_values($runs), $rtl);

        usort($runs, $rtl
            ? fn ($a, $b) => $b['x1'] <=> $a['x1']
            : fn ($a, $b) => $a['x0'] <=> $b['x0']);

        $text = '';
        $previous = null;
        foreach ($runs as $run) {
            if ($rtl && preg_match('/^[\p{P}\p{S}\p{Z}]+$/u', $run['text']) === 1 && strlen($run['text']) > 1) {
                // Browsers print a right-to-left line glyph by glyph in visual
                // order, so a run of neutrals (`" :"`) reads backwards.
                $run['text'] = implode('', array_reverse((array) preg_split('//u', $run['text'], -1, PREG_SPLIT_NO_EMPTY)));
            }
            if ($previous !== null) {
                // The same text drawn again at the same place is a fake-bold
                // or a shadow, not a second word.
                if ($run['text'] === $previous['text'] && abs($run['x0'] - $previous['x0']) < 0.3 * $size) {
                    continue;
                }
                $gap = $rtl ? $previous['x0'] - $run['x1'] : $run['x0'] - $previous['x1'];
                $boundary = str_ends_with($text, ' ') || str_starts_with($run['text'], ' ');
                if ($gap > 0.12 * $size && ! $boundary) {
                    $text .= ' ';
                }
            }
            $text .= $run['text'];
            $previous = $run;
        }

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * A vowel sign or diacritic drawn as a run of its own (Thaana fili,
     * Arabic harakat — browsers draw each glyph separately) belongs to a base
     * letter, and its x position is not a reliable guide to which: marks are
     * placed by their own anchors. Draw order is: a right-to-left line is
     * drawn in visual order, mark then base; a left-to-right line, base then
     * mark. Attached that way, the mark stays with its letter through the
     * sort.
     *
     * @param  list<array{text: string, x0: float, x1: float, y: float, size: float}>  $runs
     * @return list<array{text: string, x0: float, x1: float, y: float, size: float}>
     */
    private function attachCombiningMarks(array $runs, bool $rtl): array
    {
        $out = [];
        $pending = '';
        foreach ($runs as $run) {
            if (preg_match('/^\p{M}+$/u', $run['text']) === 1) {
                if ($rtl) {
                    $pending .= $run['text'];
                } elseif ($out !== []) {
                    $out[count($out) - 1]['text'] .= $run['text'];
                } else {
                    $pending .= $run['text'];
                }

                continue;
            }
            if ($pending !== '') {
                $run['text'] .= $pending;
                $pending = '';
            }
            $out[] = $run;
        }
        if ($pending !== '' && $out !== []) {
            $out[count($out) - 1]['text'] .= $pending;
        }

        return $out;
    }
}
