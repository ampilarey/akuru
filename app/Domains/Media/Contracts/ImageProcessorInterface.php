<?php

namespace App\Domains\Media\Contracts;

interface ImageProcessorInterface
{
    public function getWebPPath(string $storagePath): ?string;

    /**
     * @param  array{background?: string, lines?: list<array{text: string, font: string, size: int, color: string, x: int, y: int, align?: string}>}  $spec
     */
    public function renderSquarePng(int $size, array $spec): string;
}
