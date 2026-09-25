<?php

namespace App\Support\Pdf;

/** An indirect object reference (`12 0 R`). Resolved through `PdfDocument`. */
final class PdfRef
{
    public function __construct(public readonly int $number, public readonly int $generation = 0) {}
}
