<?php

namespace App\Support\Pdf;

/**
 * Turns the bytes of a text-showing operator into characters and advances.
 *
 * Simple fonts (Type1, TrueType, Type3) take one byte per code, mapped
 * through `/ToUnicode` when the producer wrote one, else through the base
 * encoding and `/Differences`. Composite (Type0) fonts take the code length
 * from their CMap — `Identity-H` is two bytes; an embedded CMap stream says
 * for itself — and read characters from `/ToUnicode`, which is the only
 * place a subset-embedded CID font records what its glyphs mean.
 *
 * Widths come along so the interpreter can keep its place on the line: they
 * decide where a space belongs between two runs of text.
 */
final class PdfFont
{
    private bool $composite = false;

    /** @var list<array{0: int, 1: int, 2: int}> [byte length, low, high] */
    private array $codespace = [];

    /** @var array<int, string> code → text */
    private array $toUnicode = [];

    private bool $hasToUnicode = false;

    /** @var list<array{0: int, 1: int, 2: int}> [low, high, first cid] */
    private array $cidRanges = [];

    /** @var array<int, string>|null code → text for simple fonts */
    private ?array $encoding = null;

    /** @var array<int, float> code (simple) or cid (composite) → width in 1/1000 text space */
    private array $widths = [];

    private float $defaultWidth = 500.0;

    private float $type3Scale = 1.0;

    private function __construct() {}

    public static function fallback(): self
    {
        $font = new self;
        $font->encoding = PdfEncodings::table('WinAnsiEncoding');
        $font->codespace = [[1, 0, 255]];

        return $font;
    }

    public static function fromDict(PdfDict $dict, PdfDocument $document): self
    {
        $font = new self;
        $subtype = $dict->name('Subtype');

        $toUnicode = $document->resolve($dict->get('ToUnicode'));
        if ($toUnicode instanceof PdfStream && ($data = $toUnicode->decoded()) !== null) {
            $ignoredCodespace = [];
            $ignoredCids = [];
            self::parseCMap($data, $ignoredCodespace, $font->toUnicode, $ignoredCids);
            $font->hasToUnicode = $font->toUnicode !== [];
        }

        if ($subtype === 'Type0') {
            $font->composite = true;
            $font->loadComposite($dict, $document);
        } else {
            $font->codespace = [[1, 0, 255]];
            $font->loadSimple($dict, $document, $subtype);
        }

        return $font;
    }

    private function loadComposite(PdfDict $dict, PdfDocument $document): void
    {
        $encoding = $document->resolve($dict->get('Encoding'));
        if ($encoding instanceof PdfStream && ($data = $encoding->decoded()) !== null) {
            $ignored = [];
            self::parseCMap($data, $this->codespace, $ignored, $this->cidRanges);
        }
        if ($this->codespace === []) {
            // Identity-H/V and the predefined CJK CMaps are all two-byte.
            $this->codespace = [[2, 0, 0xFFFF]];
        }

        $descendants = $document->resolve($dict->get('DescendantFonts'));
        $descendant = is_array($descendants) ? $document->resolve($descendants[0] ?? null) : null;
        if (! $descendant instanceof PdfDict) {
            $this->defaultWidth = 1000.0;

            return;
        }
        $this->defaultWidth = (float) ($document->resolve($descendant->get('DW')) ?? 1000);

        $w = $document->resolve($descendant->get('W'));
        if (is_array($w)) {
            $count = count($w);
            for ($i = 0; $i < $count;) {
                $first = $document->resolve($w[$i] ?? null);
                $second = $document->resolve($w[$i + 1] ?? null);
                if (! is_numeric($first)) {
                    break;
                }
                if (is_array($second)) {
                    foreach ($second as $offset => $width) {
                        $width = $document->resolve($width);
                        if (is_numeric($width)) {
                            $this->widths[(int) $first + $offset] = (float) $width;
                        }
                    }
                    $i += 2;

                    continue;
                }
                $third = $document->resolve($w[$i + 2] ?? null);
                if (is_numeric($second) && is_numeric($third)) {
                    $low = (int) $first;
                    $high = min((int) $second, $low + 65535);
                    for ($cid = $low; $cid <= $high; $cid++) {
                        $this->widths[$cid] = (float) $third;
                    }
                }
                $i += 3;
            }
        }
    }

    private function loadSimple(PdfDict $dict, PdfDocument $document, ?string $subtype): void
    {
        $descriptor = $document->resolve($dict->get('FontDescriptor'));

        // Type1 fonts default to their built-in encoding, which for the
        // standard 14 is StandardEncoding; TrueType producers mean WinAnsi.
        $base = $subtype === 'TrueType' ? 'WinAnsiEncoding' : 'StandardEncoding';
        $encoding = $document->resolve($dict->get('Encoding'));
        $differences = null;
        if ($encoding instanceof PdfName) {
            $base = $encoding->value;
        } elseif ($encoding instanceof PdfDict) {
            $baseName = $encoding->name('BaseEncoding');
            if ($baseName !== null) {
                $base = $baseName;
            }
            $differences = $document->resolve($encoding->get('Differences'));
        }
        $this->encoding = PdfEncodings::table(in_array($base, ['WinAnsiEncoding', 'MacRomanEncoding', 'MacExpertEncoding'], true) ? $base : 'StandardEncoding');

        if (is_array($differences)) {
            $code = 0;
            foreach ($differences as $entry) {
                $entry = $document->resolve($entry);
                if (is_numeric($entry)) {
                    $code = (int) $entry;
                } elseif ($entry instanceof PdfName) {
                    $glyph = PdfEncodings::glyph($entry->value);
                    if ($glyph !== null && $code >= 0 && $code < 256) {
                        $this->encoding[$code] = $glyph;
                    }
                    $code++;
                }
            }
        }

        if ($subtype === 'Type3') {
            $matrix = $document->resolve($dict->get('FontMatrix'));
            $this->type3Scale = is_array($matrix) && is_numeric($matrix[0] ?? null) ? (float) $matrix[0] * 1000 : 1.0;
        }

        $missing = $descriptor instanceof PdfDict ? $document->resolve($descriptor->get('MissingWidth')) : null;
        $this->defaultWidth = is_numeric($missing) ? (float) $missing : 500.0;

        $firstChar = (int) ($document->resolve($dict->get('FirstChar')) ?? 0);
        $widths = $document->resolve($dict->get('Widths'));
        if (is_array($widths) && $widths !== []) {
            if (! is_numeric($missing)) {
                $this->defaultWidth = 0.0;
            }
            foreach ($widths as $offset => $width) {
                $width = $document->resolve($width);
                if (is_numeric($width)) {
                    $this->widths[$firstChar + $offset] = (float) $width * $this->type3Scale;
                }
            }
        }
    }

    /**
     * Decode one string operand into glyphs.
     *
     * @return list<array{code: int, text: string, width: float, space: bool}>
     *                                                                         `width` is in 1/1000 text-space units; `space` marks a single-byte
     *                                                                         code 32, the only glyph word spacing applies to.
     */
    public function decode(string $bytes): array
    {
        $glyphs = [];
        $length = strlen($bytes);
        $pos = 0;
        while ($pos < $length) {
            [$code, $bytesUsed] = $this->nextCode($bytes, $pos, $length);
            $pos += $bytesUsed;

            $text = $this->toUnicode[$code] ?? null;
            if ($text === null) {
                $text = $this->composite ? '' : ($this->encoding[$code] ?? '');
            }
            $cid = $this->composite ? $this->cid($code) : $code;
            $glyphs[] = [
                'code' => $code,
                'text' => $text,
                'width' => $this->widths[$cid] ?? $this->defaultWidth,
                'space' => $bytesUsed === 1 && $code === 32,
            ];
        }

        return $glyphs;
    }

    /** @return array{0: int, 1: int} [code, byte length] */
    private function nextCode(string $bytes, int $pos, int $length): array
    {
        $shortest = 4;
        for ($len = 1; $len <= 4; $len++) {
            $value = null;
            foreach ($this->codespace as [$rangeLength, $low, $high]) {
                if ($rangeLength !== $len) {
                    continue;
                }
                $shortest = min($shortest, $rangeLength);
                if ($pos + $len > $length) {
                    continue;
                }
                $value ??= $this->intAt($bytes, $pos, $len);
                if ($value >= $low && $value <= $high) {
                    return [$value, $len];
                }
            }
        }
        // Outside every range: the spec says use the shortest codespace length.
        $len = min($shortest, max(1, $length - $pos));

        return [$this->intAt($bytes, $pos, $len), $len];
    }

    private function intAt(string $bytes, int $pos, int $len): int
    {
        $value = 0;
        for ($i = 0; $i < $len; $i++) {
            $value = ($value << 8) | ord($bytes[$pos + $i] ?? "\0");
        }

        return $value;
    }

    private function cid(int $code): int
    {
        foreach ($this->cidRanges as [$low, $high, $first]) {
            if ($code >= $low && $code <= $high) {
                return $first + ($code - $low);
            }
        }

        return $code;
    }

    /**
     * Read a CMap (ToUnicode or encoding): codespace ranges, bfchar/bfrange
     * mappings to text, cidchar/cidrange mappings to CIDs.
     *
     * @param  list<array{0: int, 1: int, 2: int}>  $codespace
     * @param  array<int, string>  $map
     * @param  list<array{0: int, 1: int, 2: int}>  $cidRanges
     */
    public static function parseCMap(string $data, array &$codespace, array &$map, array &$cidRanges): void
    {
        $lexer = new PdfLexer($data);
        $operands = [];
        $entries = 0;
        while (true) {
            $token = PdfParser::parse($lexer, false);
            if ($token === null && $lexer->pos >= $lexer->end()) {
                break;
            }
            if (! $token instanceof PdfKeyword) {
                $operands[] = $token;
                if (count($operands) > 64) {
                    array_shift($operands);
                }

                continue;
            }
            $section = $token->value;
            if ($section === 'begincodespacerange') {
                foreach (self::section($lexer, 'endcodespacerange', 2) as [$low, $high]) {
                    if (is_string($low) && is_string($high) && $low !== '') {
                        $len = min(4, strlen($low));
                        $codespace[] = [$len, self::int($low), self::int($high)];
                    }
                }
            } elseif ($section === 'beginbfchar') {
                foreach (self::section($lexer, 'endbfchar', 2) as [$src, $dst]) {
                    if (is_string($src) && $src !== '' && ++$entries < 300000) {
                        $map[self::int($src)] = self::text($dst);
                    }
                }
            } elseif ($section === 'beginbfrange') {
                foreach (self::section($lexer, 'endbfrange', 3) as [$low, $high, $dst]) {
                    if (! is_string($low) || ! is_string($high) || $low === '') {
                        continue;
                    }
                    $from = self::int($low);
                    $to = min(self::int($high), $from + 65535);
                    if (is_array($dst)) {
                        foreach ($dst as $offset => $item) {
                            if ($from + $offset > $to || ++$entries > 300000) {
                                break;
                            }
                            $map[$from + $offset] = self::text($item);
                        }
                    } elseif (is_string($dst)) {
                        $units = self::utf16Units($dst);
                        for ($code = $from; $code <= $to; $code++) {
                            if (++$entries > 300000) {
                                break;
                            }
                            $map[$code] = self::fromUnits($units);
                            if ($units !== []) {
                                $units[count($units) - 1]++;
                            }
                        }
                    }
                }
            } elseif ($section === 'begincidrange') {
                foreach (self::section($lexer, 'endcidrange', 3) as [$low, $high, $cid]) {
                    if (is_string($low) && is_string($high) && is_numeric($cid) && $low !== '') {
                        $cidRanges[] = [self::int($low), self::int($high), (int) $cid];
                        if (count($codespace) === 0) {
                            $codespace[] = [min(4, strlen($low)), self::int($low), self::int($high)];
                        }
                    }
                }
            } elseif ($section === 'begincidchar') {
                foreach (self::section($lexer, 'endcidchar', 2) as [$src, $cid]) {
                    if (is_string($src) && is_numeric($cid) && $src !== '') {
                        $cidRanges[] = [self::int($src), self::int($src), (int) $cid];
                    }
                }
            } elseif ($section === 'endcmap') {
                break;
            }
            $operands = [];
        }
    }

    /**
     * Rows of `$arity` operands up to the closing keyword.
     *
     * @return list<list<mixed>>
     */
    private static function section(PdfLexer $lexer, string $end, int $arity): array
    {
        $rows = [];
        $row = [];
        while (true) {
            $token = PdfParser::parse($lexer, false);
            if ($token === null && $lexer->pos >= $lexer->end()) {
                break;
            }
            if ($token instanceof PdfKeyword) {
                if ($token->value === $end || str_starts_with($token->value, 'end') || str_starts_with($token->value, 'begin')) {
                    break;
                }

                continue;
            }
            $row[] = $token;
            if (count($row) === $arity) {
                $rows[] = $row;
                $row = [];
            }
            if (count($rows) > 70000) {
                break;
            }
        }

        return $rows;
    }

    private static function int(string $bytes): int
    {
        $value = 0;
        $length = min(4, strlen($bytes));
        for ($i = 0; $i < $length; $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
        }

        return $value;
    }

    private static function text(mixed $dst): string
    {
        if ($dst instanceof PdfName) {
            return PdfEncodings::glyph($dst->value) ?? '';
        }
        if (! is_string($dst)) {
            return '';
        }

        return self::fromUnits(self::utf16Units($dst));
    }

    /** @return list<int> */
    private static function utf16Units(string $bytes): array
    {
        if (strlen($bytes) === 1) {
            return [ord($bytes)];
        }
        $units = [];
        $length = strlen($bytes) - (strlen($bytes) % 2);
        for ($i = 0; $i < $length; $i += 2) {
            $units[] = (ord($bytes[$i]) << 8) | ord($bytes[$i + 1]);
        }

        return $units;
    }

    /** @param  list<int>  $units */
    private static function fromUnits(array $units): string
    {
        $out = '';
        $count = count($units);
        for ($i = 0; $i < $count; $i++) {
            $unit = $units[$i];
            if ($unit >= 0xD800 && $unit <= 0xDBFF && $i + 1 < $count && $units[$i + 1] >= 0xDC00 && $units[$i + 1] <= 0xDFFF) {
                $out .= PdfEncodings::codePoint(0x10000 + (($unit - 0xD800) << 10) + ($units[$i + 1] - 0xDC00));
                $i++;

                continue;
            }
            $out .= PdfEncodings::codePoint($unit);
        }

        return $out;
    }
}
