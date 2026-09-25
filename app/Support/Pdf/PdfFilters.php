<?php

namespace App\Support\Pdf;

/**
 * Stream filters (ISO 32000-1 §7.4). The text path needs the lossless
 * ones: Flate (with PNG/TIFF predictors, which object and cross-reference
 * streams use), LZW, ASCIIHex, ASCII85 and RunLength. Image codecs (DCT,
 * JPX, CCITT, JBIG2) carry no text and decode to null.
 */
final class PdfFilters
{
    public static function decode(string $data, mixed $filter, mixed $parms, PdfDocument $document): ?string
    {
        $filters = $filter === null ? [] : (is_array($filter) ? $filter : [$filter]);
        $paramsList = is_array($parms) ? $parms : [$parms];

        foreach ($filters as $index => $name) {
            $name = $document->resolve($name);
            if (! $name instanceof PdfName) {
                continue;
            }
            $params = $document->resolve($paramsList[$index] ?? (count($filters) === 1 ? ($paramsList[0] ?? null) : null));
            $params = $params instanceof PdfDict ? $params : new PdfDict;

            $data = match ($name->value) {
                'FlateDecode', 'Fl' => self::predictor(self::flate($data), $params, $document),
                'LZWDecode', 'LZW' => self::predictor(self::lzw($data, (int) ($document->resolve($params->get('EarlyChange')) ?? 1)), $params, $document),
                'ASCIIHexDecode', 'AHx' => self::asciiHex($data),
                'ASCII85Decode', 'A85' => self::ascii85($data),
                'RunLengthDecode', 'RL' => self::runLength($data),
                'Crypt' => $data,
                default => null,
            };
            if ($data === null) {
                return null;
            }
        }

        return $data;
    }

    public static function flate(string $data): ?string
    {
        if ($data === '') {
            return '';
        }
        // Leading whitespace before the zlib header is a known producer bug.
        $data = ltrim($data, "\r\n\t ");
        $out = @gzuncompress($data);
        if ($out === false) {
            $out = @zlib_decode($data);
        }
        if ($out === false) {
            // Raw deflate without the zlib wrapper, or a stream cut short:
            // inflate what is there.
            $out = @gzinflate(substr($data, 2));
        }
        if ($out === false) {
            $out = self::inflatePartial($data);
        }

        return $out === false ? null : $out;
    }

    /** Recover the readable prefix of a truncated or corrupt Flate stream. */
    private static function inflatePartial(string $data): string|false
    {
        $context = @inflate_init(ZLIB_ENCODING_DEFLATE);
        if ($context === false) {
            return false;
        }
        $out = @inflate_add($context, $data, ZLIB_SYNC_FLUSH);

        return $out === false || $out === '' ? false : $out;
    }

    private static function predictor(?string $data, PdfDict $params, PdfDocument $document): ?string
    {
        if ($data === null) {
            return null;
        }
        $predictor = (int) ($document->resolve($params->get('Predictor')) ?? 1);
        if ($predictor <= 1) {
            return $data;
        }
        $colors = max(1, (int) ($document->resolve($params->get('Colors')) ?? 1));
        $bpc = max(1, (int) ($document->resolve($params->get('BitsPerComponent')) ?? 8));
        $columns = max(1, (int) ($document->resolve($params->get('Columns')) ?? 1));
        $bpp = max(1, intdiv($colors * $bpc + 7, 8));
        $rowLength = intdiv($columns * $colors * $bpc + 7, 8);

        if ($predictor === 2) {
            if ($bpc !== 8) {
                return $data;
            }
            $rows = str_split($data, $rowLength);
            foreach ($rows as &$row) {
                for ($i = $bpp; $i < strlen($row); $i++) {
                    $row[$i] = chr((ord($row[$i]) + ord($row[$i - $bpp])) & 0xFF);
                }
            }

            return implode('', $rows);
        }

        // PNG predictors: every row starts with its filter-type byte.
        $out = '';
        $previous = str_repeat("\0", $rowLength);
        $offset = 0;
        $length = strlen($data);
        while ($offset + 1 <= $length) {
            $type = ord($data[$offset]);
            $row = substr($data, $offset + 1, $rowLength);
            $offset += 1 + $rowLength;
            if (strlen($row) < $rowLength) {
                $row = str_pad($row, $rowLength, "\0");
            }
            for ($i = 0; $i < $rowLength; $i++) {
                $raw = ord($row[$i]);
                $left = $i >= $bpp ? ord($row[$i - $bpp]) : 0;
                $up = ord($previous[$i]);
                $upLeft = $i >= $bpp ? ord($previous[$i - $bpp]) : 0;
                $value = match ($type) {
                    1 => $raw + $left,
                    2 => $raw + $up,
                    3 => $raw + intdiv($left + $up, 2),
                    4 => $raw + self::paeth($left, $up, $upLeft),
                    default => $raw,
                };
                $row[$i] = chr($value & 0xFF);
            }
            $out .= $row;
            $previous = $row;
        }

        return $out;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);
        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }

        return $pb <= $pc ? $b : $c;
    }

    public static function asciiHex(string $data): string
    {
        $end = strpos($data, '>');
        if ($end !== false) {
            $data = substr($data, 0, $end);
        }
        $hex = (string) preg_replace('/[^0-9A-Fa-f]/', '', $data);
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        return $hex === '' ? '' : (string) hex2bin($hex);
    }

    public static function ascii85(string $data): string
    {
        $data = (string) preg_replace('/\s/', '', $data);
        if (str_starts_with($data, '<~')) {
            $data = substr($data, 2);
        }
        $end = strpos($data, '~>');
        if ($end !== false) {
            $data = substr($data, 0, $end);
        }
        $out = '';
        $tuple = [];
        $length = strlen($data);
        for ($i = 0; $i < $length; $i++) {
            $c = $data[$i];
            if ($c === 'z' && $tuple === []) {
                $out .= "\0\0\0\0";

                continue;
            }
            $v = ord($c) - 33;
            if ($v < 0 || $v > 84) {
                continue;
            }
            $tuple[] = $v;
            if (count($tuple) === 5) {
                $out .= self::ascii85Group($tuple, 4);
                $tuple = [];
            }
        }
        if ($tuple !== []) {
            $n = count($tuple) - 1;
            while (count($tuple) < 5) {
                $tuple[] = 84;
            }
            $out .= self::ascii85Group($tuple, $n);
        }

        return $out;
    }

    /** @param  list<int>  $tuple */
    private static function ascii85Group(array $tuple, int $bytes): string
    {
        $value = 0;
        foreach ($tuple as $digit) {
            $value = $value * 85 + $digit;
        }

        return substr(pack('N', $value & 0xFFFFFFFF), 0, $bytes);
    }

    public static function runLength(string $data): string
    {
        $out = '';
        $i = 0;
        $length = strlen($data);
        while ($i < $length) {
            $n = ord($data[$i++]);
            if ($n === 128) {
                break;
            }
            if ($n < 128) {
                $out .= substr($data, $i, $n + 1);
                $i += $n + 1;
            } else {
                if ($i < $length) {
                    $out .= str_repeat($data[$i], 257 - $n);
                }
                $i++;
            }
        }

        return $out;
    }

    public static function lzw(string $data, int $earlyChange = 1): string
    {
        $out = '';
        $dictionary = [];
        $reset = function () use (&$dictionary) {
            $dictionary = [];
            for ($i = 0; $i < 256; $i++) {
                $dictionary[$i] = chr($i);
            }
            // 256 is clear-table and 257 end-of-data; new entries start at 258.
            $dictionary[256] = '';
            $dictionary[257] = '';
        };
        $reset();
        $codeLength = 9;
        $previous = null;
        $bitBuffer = 0;
        $bitCount = 0;
        $length = strlen($data);
        $i = 0;
        while (true) {
            while ($bitCount < $codeLength && $i < $length) {
                $bitBuffer = (($bitBuffer << 8) | ord($data[$i++])) & 0xFFFFFFFF;
                $bitCount += 8;
            }
            if ($bitCount < $codeLength) {
                break;
            }
            $code = ($bitBuffer >> ($bitCount - $codeLength)) & ((1 << $codeLength) - 1);
            $bitCount -= $codeLength;

            if ($code === 256) {
                $reset();
                $codeLength = 9;
                $previous = null;

                continue;
            }
            if ($code === 257) {
                break;
            }

            if ($previous === null) {
                $entry = $dictionary[$code] ?? '';
            } elseif (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
                $dictionary[] = $previous.$entry[0];
            } else {
                $entry = $previous.$previous[0];
                $dictionary[] = $entry;
            }
            $out .= $entry;
            $previous = $entry;

            $size = count($dictionary) + $earlyChange;
            if ($size >= 4096) {
                $codeLength = 12;
            } elseif ($size >= 2048) {
                $codeLength = 12;
            } elseif ($size >= 1024) {
                $codeLength = 11;
            } elseif ($size >= 512) {
                $codeLength = 10;
            }
        }

        return $out;
    }
}
