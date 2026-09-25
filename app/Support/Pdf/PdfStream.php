<?php

namespace App\Support\Pdf;

/** A stream object: its dictionary and its raw (still encoded) bytes. */
final class PdfStream
{
    private ?string $decoded = null;

    private bool $decodeTried = false;

    public function __construct(
        public readonly PdfDict $dict,
        public readonly string $raw,
        private readonly PdfDocument $document,
    ) {}

    /**
     * The stream's bytes with every filter undone, or null when a filter is
     * one this reader does not decode (image codecs) or the data is corrupt.
     */
    public function decoded(): ?string
    {
        if (! $this->decodeTried) {
            $this->decodeTried = true;
            $this->decoded = PdfFilters::decode(
                $this->raw,
                $this->document->resolve($this->dict->get('Filter')),
                $this->document->resolve($this->dict->get('DecodeParms') ?? $this->dict->get('DP')),
                $this->document,
            );
        }

        return $this->decoded;
    }
}
