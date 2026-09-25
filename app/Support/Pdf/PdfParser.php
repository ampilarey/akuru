<?php

namespace App\Support\Pdf;

/**
 * Builds PDF objects out of `PdfLexer` tokens: numbers, names, strings,
 * arrays (PHP lists), dictionaries (`PdfDict`), references (`PdfRef`) and
 * keywords (`PdfKeyword`, which is how content-stream operators come out).
 */
final class PdfParser
{
    /**
     * Parse the next object. `$allowRefs` is off inside content streams and
     * CMaps, where `1 0 R` cannot occur and `1 0 Td` must not be mistaken
     * for one.
     */
    public static function parse(PdfLexer $lexer, bool $allowRefs = true, int $depth = 0): mixed
    {
        [$type, $value] = $lexer->next();

        return self::fromToken($lexer, $type, $value, $allowRefs, $depth);
    }

    public static function fromToken(PdfLexer $lexer, string $type, mixed $value, bool $allowRefs, int $depth): mixed
    {
        switch ($type) {
            case 'eof':
                return null;
            case 'num':
                if ($allowRefs && is_int($value) && $value >= 0) {
                    $save = $lexer->pos;
                    [$t2, $v2] = $lexer->next();
                    if ($t2 === 'num' && is_int($v2) && $v2 >= 0) {
                        [$t3, $v3] = $lexer->next();
                        if ($t3 === 'kw' && $v3 === 'R') {
                            return new PdfRef($value, $v2);
                        }
                    }
                    $lexer->pos = $save;
                }

                return $value;
            case 'name':
                return new PdfName($value);
            case 'str':
                return $value;
            case 'kw':
                return match ($value) {
                    'true' => true,
                    'false' => false,
                    'null' => null,
                    default => new PdfKeyword($value),
                };
            case 'delim':
                if ($depth > 200) {
                    return null;
                }
                if ($value === '[') {
                    $items = [];
                    while (true) {
                        [$t, $v] = $lexer->next();
                        if ($t === 'eof' || ($t === 'delim' && $v === ']')) {
                            break;
                        }
                        if ($t === 'delim' && ($v === '>>' || $v === '}')) {
                            continue;
                        }
                        $items[] = self::fromToken($lexer, $t, $v, $allowRefs, $depth + 1);
                    }

                    return $items;
                }
                if ($value === '<<') {
                    $entries = [];
                    while (true) {
                        [$t, $v] = $lexer->next();
                        if ($t === 'eof' || ($t === 'delim' && $v === '>>')) {
                            break;
                        }
                        if ($t !== 'name') {
                            // Malformed: a value without a key. Consume it and move on.
                            self::fromToken($lexer, $t, $v, $allowRefs, $depth + 1);

                            continue;
                        }
                        $entries[$v] = self::parse($lexer, $allowRefs, $depth + 1);
                    }

                    return new PdfDict($entries);
                }
                if ($value === '{') {
                    // PostScript procedure (Type 4 functions): read and drop.
                    $items = [];
                    while (true) {
                        [$t, $v] = $lexer->next();
                        if ($t === 'eof' || ($t === 'delim' && $v === '}')) {
                            break;
                        }
                        $items[] = self::fromToken($lexer, $t, $v, false, $depth + 1);
                    }

                    return $items;
                }

                // A stray `]`, `>>` or `}`: nothing to build from it.
                return new PdfKeyword($value);
        }

        return null;
    }
}
