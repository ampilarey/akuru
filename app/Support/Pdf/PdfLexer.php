<?php

namespace App\Support\Pdf;

/**
 * Tokeniser for PDF object syntax (ISO 32000-1 §7.2–7.3). The same lexer
 * reads file-level objects, object streams, CMaps and page content streams;
 * only the parser above it decides what a keyword means.
 *
 * Tokens are `[type, value]` pairs: `num` (int|float), `name` (string, the
 * slash removed and `#xx` escapes applied), `str` (raw bytes, for both `(..)`
 * literal and `<..>` hex strings), `delim` (`[`, `]`, `<<`, `>>`, `{`, `}`),
 * `kw` (a bare keyword) and `eof`.
 */
final class PdfLexer
{
    private const WHITESPACE = " \t\r\n\f\0";

    private const DELIMITERS = '()<>[]{}/%';

    public int $pos;

    private readonly int $end;

    public function __construct(private readonly string $source, int $pos = 0, ?int $end = null)
    {
        $this->pos = $pos;
        $this->end = $end ?? strlen($source);
    }

    public function source(): string
    {
        return $this->source;
    }

    public function end(): int
    {
        return $this->end;
    }

    public function skipWhitespace(): void
    {
        while ($this->pos < $this->end) {
            $c = $this->source[$this->pos];
            if (str_contains(self::WHITESPACE, $c)) {
                $this->pos++;

                continue;
            }
            if ($c === '%') {
                while ($this->pos < $this->end && $this->source[$this->pos] !== "\n" && $this->source[$this->pos] !== "\r") {
                    $this->pos++;
                }

                continue;
            }
            break;
        }
    }

    /** @return array{0: string, 1: mixed} */
    public function next(): array
    {
        $this->skipWhitespace();
        if ($this->pos >= $this->end) {
            return ['eof', null];
        }

        $c = $this->source[$this->pos];

        switch ($c) {
            case '/':
                $this->pos++;

                return ['name', $this->decodeName($this->readRegular())];
            case '(':
                $this->pos++;

                return ['str', $this->readLiteralString()];
            case '<':
                if (($this->source[$this->pos + 1] ?? '') === '<') {
                    $this->pos += 2;

                    return ['delim', '<<'];
                }
                $this->pos++;

                return ['str', $this->readHexString()];
            case '>':
                if (($this->source[$this->pos + 1] ?? '') === '>') {
                    $this->pos += 2;

                    return ['delim', '>>'];
                }
                // A stray `>`: skip it and carry on.
                $this->pos++;

                return $this->next();
            case '[':
            case ']':
            case '{':
            case '}':
                $this->pos++;

                return ['delim', $c];
            case ')':
                $this->pos++;

                return $this->next();
        }

        $word = $this->readRegular();
        if ($word === '') {
            // A delimiter this switch does not know; never loop on it.
            $this->pos++;

            return $this->next();
        }

        if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/', $word) === 1) {
            return ['num', str_contains($word, '.') ? (float) $word : (int) $word];
        }
        if (preg_match('/^[+-.\d]+$/', $word) === 1) {
            // Malformed numbers such as `--5` or `3.4.5` are read as zero by
            // every viewer; so here.
            return ['num', (float) $word];
        }

        return ['kw', $word];
    }

    /** Read a run of regular (non-whitespace, non-delimiter) characters. */
    private function readRegular(): string
    {
        $start = $this->pos;
        while ($this->pos < $this->end) {
            $c = $this->source[$this->pos];
            if (str_contains(self::WHITESPACE, $c) || str_contains(self::DELIMITERS, $c)) {
                break;
            }
            $this->pos++;
        }

        return substr($this->source, $start, $this->pos - $start);
    }

    private function decodeName(string $raw): string
    {
        if (! str_contains($raw, '#')) {
            return $raw;
        }

        return (string) preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn ($m) => chr(hexdec($m[1])), $raw);
    }

    private function readLiteralString(): string
    {
        $out = '';
        $depth = 1;
        while ($this->pos < $this->end) {
            $c = $this->source[$this->pos++];
            if ($c === '\\') {
                if ($this->pos >= $this->end) {
                    break;
                }
                $e = $this->source[$this->pos++];
                switch ($e) {
                    case 'n': $out .= "\n";
                        break;
                    case 'r': $out .= "\r";
                        break;
                    case 't': $out .= "\t";
                        break;
                    case 'b': $out .= "\x08";
                        break;
                    case 'f': $out .= "\f";
                        break;
                    case "\r":
                        if (($this->source[$this->pos] ?? '') === "\n") {
                            $this->pos++;
                        }
                        break;
                    case "\n":
                        break;
                    default:
                        if ($e >= '0' && $e <= '7') {
                            $octal = $e;
                            for ($i = 0; $i < 2 && $this->pos < $this->end; $i++) {
                                $d = $this->source[$this->pos];
                                if ($d < '0' || $d > '7') {
                                    break;
                                }
                                $octal .= $d;
                                $this->pos++;
                            }
                            $out .= chr(octdec($octal) & 0xFF);
                        } else {
                            $out .= $e;
                        }
                }

                continue;
            }
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
            $out .= $c;
        }

        return $out;
    }

    private function readHexString(): string
    {
        $hex = '';
        while ($this->pos < $this->end) {
            $c = $this->source[$this->pos++];
            if ($c === '>') {
                break;
            }
            if (ctype_xdigit($c)) {
                $hex .= $c;
            }
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        return $hex === '' ? '' : (string) hex2bin($hex);
    }

    /**
     * Skip an inline image (`BI … ID <binary> EI`) from just after the `ID`
     * keyword. Binary data may contain anything, so the end is the first `EI`
     * that stands alone between whitespace.
     */
    public function skipInlineImageData(): void
    {
        if ($this->pos < $this->end && str_contains(self::WHITESPACE, $this->source[$this->pos])) {
            $this->pos++;
        }
        if (preg_match('/(?<=[\s\x00])EI(?=[\s\x00]|$)/', $this->source, $m, PREG_OFFSET_CAPTURE, $this->pos) === 1) {
            $this->pos = $m[0][1] + 2;

            return;
        }
        $this->pos = $this->end;
    }
}
