<?php

namespace App\Support\Pdf;

/**
 * A PDF dictionary. Keys are stored without the leading slash. Values are
 * unresolved: a `PdfRef` stays a `PdfRef` until `PdfDocument::resolve()`.
 */
final class PdfDict
{
    /** @param array<string, mixed> $entries */
    public function __construct(public array $entries = []) {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->entries);
    }

    public function get(string $key): mixed
    {
        return $this->entries[$key] ?? null;
    }

    /** The value of a name-valued entry, or null when absent or not a name. */
    public function name(string $key): ?string
    {
        $value = $this->entries[$key] ?? null;

        return $value instanceof PdfName ? $value->value : null;
    }
}
