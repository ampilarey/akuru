<?php

namespace App\Domains\Media\Contracts;

interface ImageProcessorInterface
{
    public function getWebPPath(string $storagePath): ?string;

    /**
     * A WebP copy of a public image no wider than `$width` pixels (never
     * enlarged), made once and kept beside the original. Null when the format
     * is not supported or conversion fails — callers fall back to the
     * original. BOOKSHOP_PLAN B1b: product cards and galleries.
     */
    public function getResizedWebPPath(string $storagePath, int $width): ?string;

    /**
     * @param  array{background?: string, lines?: list<array{text: string, font: string, size: int, color: string, x: int, y: int, align?: string}>}  $spec
     */
    public function renderSquarePng(int $size, array $spec): string;
}
