<?php

namespace App\Support\Pdf;

/** A PDF name object (`/Foo`), kept distinct from a byte string. */
final class PdfName
{
    public function __construct(public readonly string $value) {}

    public function is(string $name): bool
    {
        return $this->value === $name;
    }
}
