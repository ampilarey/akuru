<?php

namespace App\Domains\Library\Contracts;

/**
 * BACKLOG B1: text recognition (OCR) of one page picture, behind a seam the
 * Library owns (rule 4). Bound to Tesseract where `LIBRARY_OCR_TESSERACT_PATH`
 * names one; elsewhere it says it is not enabled and nothing is read.
 */
interface PageImageTextReader
{
    /** Whether this host can read text from a picture at all. */
    public function enabled(): bool;

    /** The plain text on a PNG page; an empty string when there is none. Throws when it cannot read. */
    public function text(string $png): string;
}
