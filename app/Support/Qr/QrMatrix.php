<?php

namespace App\Support\Qr;

use InvalidArgumentException;

/**
 * A QR code (ISO/IEC 18004), as a matrix of dark and light modules.
 *
 * Written here rather than pulled in as a package because the certificate
 * and ID-card QR had to become real, and Composer's GitHub-hosted QR
 * packages could not be downloaded where it was built (STATUS §5gi). It is
 * deliberately small:
 *
 * - **byte mode** only (UTF-8 bytes; a verification URL or a student number);
 * - error-correction level **M** (recovers about 15% damage: a folded
 *   certificate, a scuffed card);
 * - **versions 1–10** (up to 213 bytes at M), chosen automatically, which is
 *   far more than a verification URL needs;
 * - all eight masks, the one with the lowest penalty chosen as the standard
 *   says.
 *
 * `QrMatrixTest` compares the output module-for-module with the npm `qrcode`
 * package for several payloads and every mask, and the certificate walk reads
 * the printed code back with a real decoder.
 */
final class QrMatrix
{
    /**
     * Level M, per version: [total codewords, EC codewords per block,
     * [[blocks, data codewords per block], ...]].
     */
    private const BLOCKS_M = [
        1 => [26, 10, [[1, 16]]],
        2 => [44, 16, [[1, 28]]],
        3 => [70, 26, [[1, 44]]],
        4 => [100, 18, [[2, 32]]],
        5 => [134, 24, [[2, 43]]],
        6 => [172, 16, [[4, 27]]],
        7 => [196, 18, [[4, 31]]],
        8 => [242, 22, [[2, 38], [2, 39]]],
        9 => [292, 22, [[3, 36], [2, 37]]],
        10 => [346, 26, [[4, 43], [1, 44]]],
    ];

    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private const REMAINDER_BITS = [1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 0, 8 => 0, 9 => 0, 10 => 0];

    /** Level M's two format bits. */
    private const EC_LEVEL_BITS = 0b00;

    /** @var list<list<bool>> */
    private array $modules = [];

    /** @var list<list<bool>> true where a function pattern lives */
    private array $reserved = [];

    private int $size;

    private function __construct(public readonly int $version, public readonly int $mask)
    {
        $this->size = 17 + 4 * $version;
    }

    /**
     * @param  int|null  $mask  0–7 to force a mask (tests); null chooses by penalty
     */
    public static function encode(string $payload, ?int $mask = null, ?int $version = null): self
    {
        $version ??= self::smallestVersionFor(strlen($payload));
        if (! isset(self::BLOCKS_M[$version])) {
            throw new InvalidArgumentException('QR version must be 1–10.');
        }
        if (strlen($payload) > self::capacity($version)) {
            throw new InvalidArgumentException("A {$version}-M QR code holds at most ".self::capacity($version).' bytes.');
        }

        $codewords = self::codewords($payload, $version);

        if ($mask !== null) {
            return self::build($version, $mask, $codewords);
        }

        $best = null;
        $bestPenalty = PHP_INT_MAX;
        for ($m = 0; $m < 8; $m++) {
            $candidate = self::build($version, $m, $codewords);
            $penalty = $candidate->penalty();
            if ($penalty < $bestPenalty) {
                $best = $candidate;
                $bestPenalty = $penalty;
            }
        }

        return $best;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function isDark(int $row, int $col): bool
    {
        return $this->modules[$row][$col];
    }

    /** @return list<string> one string of 0/1 per row */
    public function rows(): array
    {
        return array_map(fn (array $row) => implode('', array_map(fn (bool $on) => $on ? '1' : '0', $row)), $this->modules);
    }

    // ── Capacity and data ──────────────────────────────────────────────────

    private static function dataCodewords(int $version): int
    {
        return array_sum(array_map(fn (array $group) => $group[0] * $group[1], self::BLOCKS_M[$version][2]));
    }

    private static function capacity(int $version): int
    {
        // mode (4 bits) + count (8 bits for versions 1–9, 16 from 10)
        $countBits = $version < 10 ? 8 : 16;

        return intdiv(self::dataCodewords($version) * 8 - 4 - $countBits, 8);
    }

    private static function smallestVersionFor(int $bytes): int
    {
        foreach (array_keys(self::BLOCKS_M) as $version) {
            if ($bytes <= self::capacity($version)) {
                return $version;
            }
        }

        throw new InvalidArgumentException('Too long for a version 1–10 QR code ('.self::capacity(10).' bytes at most).');
    }

    /** @return list<int> the final interleaved codeword sequence */
    private static function codewords(string $payload, int $version): array
    {
        $bits = '0100'; // byte mode
        $bits .= str_pad(decbin(strlen($payload)), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach (str_split($payload) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $capacityBits = self::dataCodewords($version) * 8;
        $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }

        $data = array_map('bindec', str_split($bits, 8));
        for ($pad = 0; count($data) < self::dataCodewords($version); $pad++) {
            $data[] = $pad % 2 === 0 ? 0xEC : 0x11;
        }

        [, $ecPerBlock, $groups] = self::BLOCKS_M[$version];
        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;
        foreach ($groups as [$count, $length]) {
            for ($b = 0; $b < $count; $b++) {
                $block = array_slice($data, $offset, $length);
                $offset += $length;
                $dataBlocks[] = $block;
                $ecBlocks[] = ReedSolomon::remainder($block, $ecPerBlock);
            }
        }

        $out = [];
        $longest = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $longest; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    // ── Layout ─────────────────────────────────────────────────────────────

    /** @param list<int> $codewords */
    private static function build(int $version, int $mask, array $codewords): self
    {
        $qr = new self($version, $mask);
        $qr->modules = array_fill(0, $qr->size, array_fill(0, $qr->size, false));
        $qr->reserved = $qr->modules;

        $qr->placeFinder(0, 0);
        $qr->placeFinder(0, $qr->size - 7);
        $qr->placeFinder($qr->size - 7, 0);
        $qr->placeTiming();
        $qr->placeAlignment();
        $qr->reserveFormatAndVersion();
        $qr->placeData($codewords);
        $qr->placeFormat();
        $qr->placeVersion();

        return $qr;
    }

    private function set(int $row, int $col, bool $dark, bool $reserve = true): void
    {
        $this->modules[$row][$col] = $dark;
        if ($reserve) {
            $this->reserved[$row][$col] = true;
        }
    }

    private function placeFinder(int $top, int $left): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $row = $top + $r;
                $col = $left + $c;
                if ($row < 0 || $col < 0 || $row >= $this->size || $col >= $this->size) {
                    continue;
                }
                $inside = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6;
                $dark = $inside && ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                $this->set($row, $col, $dark);
            }
        }
    }

    private function placeTiming(): void
    {
        for ($i = 8; $i < $this->size - 8; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
    }

    private function placeAlignment(): void
    {
        $centres = self::ALIGNMENT[$this->version];
        if ($centres === []) {
            return;
        }
        $first = $centres[0];
        $last = $centres[count($centres) - 1];
        foreach ($centres as $row) {
            foreach ($centres as $col) {
                // The three corners sit on the finders. Every other centre is
                // drawn, including those on the timing lines (version 7 up).
                if (($row === $first && $col === $first) || ($row === $first && $col === $last) || ($row === $last && $col === $first)) {
                    continue;
                }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $this->set($row + $r, $col + $c, max(abs($r), abs($c)) !== 1);
                    }
                }
            }
        }
    }

    private function reserveFormatAndVersion(): void
    {
        for ($i = 0; $i <= 8; $i++) {
            $this->reserved[8][$i] = true;
            $this->reserved[$i][8] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            $this->reserved[8][$this->size - 1 - $i] = true;
            $this->reserved[$this->size - 1 - $i][8] = true;
        }
        $this->set($this->size - 8, 8, true); // the dark module

        if ($this->version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $this->reserved[$i][$this->size - 11 + $j] = true;
                    $this->reserved[$this->size - 11 + $j][$i] = true;
                }
            }
        }
    }

    /** @param list<int> $codewords */
    private function placeData(array $codewords): void
    {
        $bits = '';
        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', self::REMAINDER_BITS[$this->version]);

        $index = 0;
        $upward = true;
        for ($right = $this->size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right = 5; // the vertical timing column is skipped
            }
            for ($step = 0; $step < $this->size; $step++) {
                $row = $upward ? $this->size - 1 - $step : $step;
                for ($c = 0; $c < 2; $c++) {
                    $col = $right - $c;
                    if ($this->reserved[$row][$col]) {
                        continue;
                    }
                    $dark = ($bits[$index] ?? '0') === '1';
                    $index++;
                    $this->modules[$row][$col] = $dark !== self::maskBit($this->mask, $row, $col);
                }
            }
            $upward = ! $upward;
        }
    }

    private static function maskBit(int $mask, int $i, int $j): bool
    {
        return match ($mask) {
            0 => ($i + $j) % 2 === 0,
            1 => $i % 2 === 0,
            2 => $j % 3 === 0,
            3 => ($i + $j) % 3 === 0,
            4 => (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0,
            5 => ($i * $j) % 2 + ($i * $j) % 3 === 0,
            6 => (($i * $j) % 2 + ($i * $j) % 3) % 2 === 0,
            7 => (($i + $j) % 2 + ($i * $j) % 3) % 2 === 0,
        };
    }

    private function placeFormat(): void
    {
        $data = (self::EC_LEVEL_BITS << 3) | $this->mask;
        $bits = (($data << 10) | self::bch($data << 10, 0x537, 11)) ^ 0x5412;

        for ($i = 0; $i < 15; $i++) {
            $dark = (($bits >> $i) & 1) === 1;

            // Around the top-left finder.
            if ($i < 6) {
                $this->modules[$i][8] = $dark;
            } elseif ($i < 8) {
                $this->modules[$i + 1][8] = $dark;
            } else {
                $this->modules[$this->size - 15 + $i][8] = $dark;
            }

            // Split between the other two finders.
            if ($i < 8) {
                $this->modules[8][$this->size - 1 - $i] = $dark;
            } elseif ($i < 9) {
                $this->modules[8][15 - $i - 1 + 1] = $dark;
            } else {
                $this->modules[8][15 - $i - 1] = $dark;
            }
        }

        $this->modules[$this->size - 8][8] = true;
    }

    private function placeVersion(): void
    {
        if ($this->version < 7) {
            return;
        }

        $bits = ($this->version << 12) | self::bch($this->version << 12, 0x1F25, 13);
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $row = intdiv($i, 3);
            $col = $this->size - 11 + $i % 3;
            $this->modules[$row][$col] = $dark;
            $this->modules[$col][$row] = $dark;
        }
    }

    /** Remainder of `$value` divided by `$generator` over GF(2). */
    private static function bch(int $value, int $generator, int $generatorBits): int
    {
        while (self::bitLength($value) >= $generatorBits) {
            $value ^= $generator << (self::bitLength($value) - $generatorBits);
        }

        return $value;
    }

    private static function bitLength(int $value): int
    {
        return $value === 0 ? 0 : (int) floor(log($value, 2)) + 1;
    }

    // ── Mask penalty (ISO/IEC 18004 §7.8.3) ────────────────────────────────

    private function penalty(): int
    {
        $n = $this->size;
        $penalty = 0;

        // N1: runs of five or more of one colour, in rows and columns.
        for ($a = 0; $a < $n; $a++) {
            foreach ([true, false] as $horizontal) {
                $run = 1;
                for ($b = 1; $b < $n; $b++) {
                    $current = $horizontal ? $this->modules[$a][$b] : $this->modules[$b][$a];
                    $previous = $horizontal ? $this->modules[$a][$b - 1] : $this->modules[$b - 1][$a];
                    if ($current === $previous) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $penalty += 3 + ($run - 5);
                        }
                        $run = 1;
                    }
                }
                if ($run >= 5) {
                    $penalty += 3 + ($run - 5);
                }
            }
        }

        // N2: 2×2 blocks of one colour.
        for ($r = 0; $r < $n - 1; $r++) {
            for ($c = 0; $c < $n - 1; $c++) {
                $v = $this->modules[$r][$c];
                if ($v === $this->modules[$r][$c + 1] && $v === $this->modules[$r + 1][$c] && $v === $this->modules[$r + 1][$c + 1]) {
                    $penalty += 3;
                }
            }
        }

        // N3: finder-like 1:1:3:1:1 runs with four light modules on a side.
        $patterns = ['10111010000', '00001011101'];
        for ($a = 0; $a < $n; $a++) {
            $row = '';
            $col = '';
            for ($b = 0; $b < $n; $b++) {
                $row .= $this->modules[$a][$b] ? '1' : '0';
                $col .= $this->modules[$b][$a] ? '1' : '0';
            }
            foreach ([$row, $col] as $line) {
                for ($i = 0; $i <= $n - 11; $i++) {
                    $window = substr($line, $i, 11);
                    if (in_array($window, $patterns, true)) {
                        $penalty += 40;
                    }
                }
            }
        }

        // N4: how far the dark proportion is from half.
        $dark = 0;
        foreach ($this->modules as $row) {
            foreach ($row as $on) {
                $dark += $on ? 1 : 0;
            }
        }
        $penalty += intdiv(abs($dark * 20 - $n * $n * 10), $n * $n) * 10;

        return $penalty;
    }
}
