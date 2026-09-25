<?php

namespace App\Support\Pdf;

/** A bare keyword token: `obj`, `stream`, or a content-stream operator such as `Tj`. */
final class PdfKeyword
{
    public function __construct(public readonly string $value) {}
}
