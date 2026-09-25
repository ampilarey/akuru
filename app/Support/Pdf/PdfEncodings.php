<?php

namespace App\Support\Pdf;

/**
 * Single-byte text encodings for simple fonts (ISO 32000-1 Annex D) and the
 * glyph-name → character lookup that `/Differences` arrays need.
 *
 * WinAnsi is Windows-1252 and MacRoman is Mac OS Roman, both of which the
 * runtime already knows; only StandardEncoding's upper half is spelled out.
 * The glyph list is not Adobe's 4,000-name table: it covers ASCII, the
 * punctuation and ligature names every Latin font uses, and builds accented
 * letters from their parts (`eacute` = `e` + combining acute, then NFC),
 * which is how the names are formed.
 */
final class PdfEncodings
{
    /** @var array<int, string> */
    private const STANDARD_UPPER = [
        0x27 => '’', 0x60 => '‘',
        0xA1 => '¡', 0xA2 => '¢', 0xA3 => '£', 0xA4 => '⁄', 0xA5 => '¥', 0xA6 => 'ƒ', 0xA7 => '§', 0xA8 => '¤',
        0xA9 => "'", 0xAA => '“', 0xAB => '«', 0xAC => '‹', 0xAD => '›', 0xAE => 'ﬁ', 0xAF => 'ﬂ',
        0xB1 => '–', 0xB2 => '†', 0xB3 => '‡', 0xB4 => '·', 0xB6 => '¶', 0xB7 => '•', 0xB8 => '‚', 0xB9 => '„',
        0xBA => '”', 0xBB => '»', 0xBC => '…', 0xBD => '‰', 0xBF => '¿',
        0xC1 => '`', 0xC2 => '´', 0xC3 => 'ˆ', 0xC4 => '˜', 0xC5 => '¯', 0xC6 => '˘', 0xC7 => '˙', 0xC8 => '¨',
        0xCA => '˚', 0xCB => '¸', 0xCD => '˝', 0xCE => '˛', 0xCF => 'ˇ', 0xD0 => '—',
        0xE1 => 'Æ', 0xE3 => 'ª', 0xE8 => 'Ł', 0xE9 => 'Ø', 0xEA => 'Œ', 0xEB => 'º',
        0xF1 => 'æ', 0xF5 => 'ı', 0xF8 => 'ł', 0xF9 => 'ø', 0xFA => 'œ', 0xFB => 'ß',
    ];

    /** @var array<string, string> */
    private const GLYPHS = [
        'space' => ' ', 'exclam' => '!', 'quotedbl' => '"', 'numbersign' => '#', 'dollar' => '$', 'percent' => '%',
        'ampersand' => '&', 'quotesingle' => "'", 'parenleft' => '(', 'parenright' => ')', 'asterisk' => '*',
        'plus' => '+', 'comma' => ',', 'hyphen' => '-', 'period' => '.', 'slash' => '/', 'colon' => ':',
        'semicolon' => ';', 'less' => '<', 'equal' => '=', 'greater' => '>', 'question' => '?', 'at' => '@',
        'bracketleft' => '[', 'backslash' => '\\', 'bracketright' => ']', 'asciicircum' => '^', 'underscore' => '_',
        'grave' => '`', 'braceleft' => '{', 'bar' => '|', 'braceright' => '}', 'asciitilde' => '~',
        'zero' => '0', 'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4', 'five' => '5', 'six' => '6',
        'seven' => '7', 'eight' => '8', 'nine' => '9',
        'quoteright' => '’', 'quoteleft' => '‘', 'quotedblleft' => '“', 'quotedblright' => '”', 'quotedblbase' => '„',
        'quotesinglbase' => '‚', 'endash' => '–', 'emdash' => '—', 'bullet' => '•', 'ellipsis' => '…',
        'fi' => 'ﬁ', 'fl' => 'ﬂ', 'ff' => 'ﬀ', 'ffi' => 'ﬃ', 'ffl' => 'ﬄ', 'dagger' => '†', 'daggerdbl' => '‡',
        'section' => '§', 'paragraph' => '¶', 'periodcentered' => '·', 'copyright' => '©', 'registered' => '®',
        'trademark' => '™', 'degree' => '°', 'plusminus' => '±', 'multiply' => '×', 'divide' => '÷', 'minus' => '−',
        'fraction' => '⁄', 'guillemotleft' => '«', 'guillemotright' => '»', 'guilsinglleft' => '‹', 'guilsinglright' => '›',
        'exclamdown' => '¡', 'questiondown' => '¿', 'cent' => '¢', 'sterling' => '£', 'yen' => '¥', 'Euro' => '€',
        'currency' => '¤', 'florin' => 'ƒ', 'nbspace' => ' ', 'nonbreakingspace' => ' ', 'brokenbar' => '¦',
        'logicalnot' => '¬', 'macron' => '¯', 'acute' => '´', 'dieresis' => '¨', 'cedilla' => '¸', 'onesuperior' => '¹',
        'twosuperior' => '²', 'threesuperior' => '³', 'onequarter' => '¼', 'onehalf' => '½', 'threequarters' => '¾',
        'ordfeminine' => 'ª', 'ordmasculine' => 'º', 'mu' => 'µ', 'perthousand' => '‰', 'circumflex' => 'ˆ', 'tilde' => '˜',
        'AE' => 'Æ', 'ae' => 'æ', 'OE' => 'Œ', 'oe' => 'œ', 'Oslash' => 'Ø', 'oslash' => 'ø', 'Thorn' => 'Þ', 'thorn' => 'þ',
        'Eth' => 'Ð', 'eth' => 'ð', 'germandbls' => 'ß', 'dotlessi' => 'ı', 'Lslash' => 'Ł', 'lslash' => 'ł',
        'hyphenminus' => '-', 'softhyphen' => '', 'sfthyphen' => '', 'apostrophe' => "'", 'quotereversed' => '‛',
        'arrowright' => '→', 'arrowleft' => '←', 'checkmark' => '✓', 'infinity' => '∞', 'lessequal' => '≤',
        'greaterequal' => '≥', 'notequal' => '≠', 'summation' => '∑', 'radical' => '√', 'partialdiff' => '∂',
        'Delta' => 'Δ', 'Omega' => 'Ω', 'pi' => 'π', 'alpha' => 'α', 'beta' => 'β', 'gamma' => 'γ', 'delta' => 'δ',
        'lambda' => 'λ', 'sigma' => 'σ', 'theta' => 'θ', 'omega' => 'ω', 'epsilon' => 'ε',
    ];

    /** @var array<string, string> accent glyph-name suffix → combining mark */
    private const ACCENTS = [
        'acute' => "\u{0301}", 'grave' => "\u{0300}", 'circumflex' => "\u{0302}", 'tilde' => "\u{0303}",
        'dieresis' => "\u{0308}", 'ring' => "\u{030A}", 'cedilla' => "\u{0327}", 'caron' => "\u{030C}",
        'macron' => "\u{0304}", 'breve' => "\u{0306}", 'dotaccent' => "\u{0307}", 'ogonek' => "\u{0328}",
        'hungarumlaut' => "\u{030B}", 'commaaccent' => "\u{0326}",
    ];

    /** @var array<string, array<int, string>> */
    private static array $tables = [];

    /**
     * The full 256-entry table for a named base encoding.
     *
     * @return array<int, string>
     */
    public static function table(string $name): array
    {
        if (isset(self::$tables[$name])) {
            return self::$tables[$name];
        }
        $table = [];
        for ($code = 0; $code < 256; $code++) {
            $table[$code] = self::single($name, $code);
        }

        return self::$tables[$name] = $table;
    }

    private static function single(string $encoding, int $code): string
    {
        if ($code < 0x20) {
            return '';
        }
        $byte = chr($code);
        switch ($encoding) {
            case 'WinAnsiEncoding':
                if ($code === 0xA0) {
                    return ' ';
                }
                if ($code === 0xAD) {
                    return '-';
                }
                $out = @mb_convert_encoding($byte, 'UTF-8', 'Windows-1252');

                return is_string($out) && $out !== '' && $out !== '?' ? $out : ($code < 0x80 ? $byte : '•');
            case 'MacRomanEncoding':
            case 'MacExpertEncoding':
                $out = function_exists('iconv') ? @iconv('MACINTOSH', 'UTF-8//IGNORE', $byte) : false;
                if (is_string($out) && $out !== '') {
                    return $out;
                }

                return $code < 0x80 ? $byte : '';
            default:
                if (isset(self::STANDARD_UPPER[$code])) {
                    return self::STANDARD_UPPER[$code];
                }

                return $code < 0x7F ? $byte : '';
        }
    }

    /** The character a glyph name stands for, or null when unknown. */
    public static function glyph(string $name): ?string
    {
        if (isset(self::GLYPHS[$name])) {
            return self::GLYPHS[$name];
        }
        if (strlen($name) === 1) {
            return $name;
        }
        if (preg_match('/^uni([0-9A-Fa-f]{4})/', $name, $m) === 1) {
            return self::codePoint((int) hexdec($m[1]));
        }
        if (preg_match('/^u([0-9A-Fa-f]{4,6})$/', $name, $m) === 1) {
            return self::codePoint((int) hexdec($m[1]));
        }
        // `eacute`, `Ntilde`, `ccedilla`, `Scaron`, `uhungarumlaut` …
        if (preg_match('/^([A-Za-z])([a-z]+)$/', $name, $m) === 1 && isset(self::ACCENTS[$m[2]])) {
            $composed = $m[1].self::ACCENTS[$m[2]];

            return class_exists(\Normalizer::class) ? (\Normalizer::normalize($composed, \Normalizer::FORM_C) ?: $composed) : $composed;
        }
        // Small-cap and variant suffixes: `a.sc`, `one.oldstyle`, `T_h`.
        $dot = strpos($name, '.');
        if ($dot !== false && $dot > 0) {
            return self::glyph(substr($name, 0, $dot));
        }
        if (str_contains($name, '_')) {
            $parts = array_map(fn ($part) => self::glyph($part), explode('_', $name));
            if (! in_array(null, $parts, true)) {
                return implode('', $parts);
            }
        }

        return null;
    }

    public static function codePoint(int $point): string
    {
        if ($point <= 0 || $point > 0x10FFFF || ($point >= 0xD800 && $point <= 0xDFFF)) {
            return '';
        }

        return (string) mb_chr($point, 'UTF-8');
    }
}
