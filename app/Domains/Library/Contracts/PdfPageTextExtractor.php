<?php

namespace App\Domains\Library\Contracts;

/**
 * Turns a PDF's bytes into the text of each page, in order.
 *
 * LIBRARY_PLAN §36: a PDF original goes to private storage and the reader
 * serves pages, never the file. This is the seam between the two (rule 4):
 * the Library asks for pages and does not care whether they came from a
 * pure-PHP parser (`App\Support\Pdf\PdfTextExtractor`, today's binding) or a
 * rasteriser on a host that has one.
 */
interface PdfPageTextExtractor
{
    /**
     * @return list<string> plain text per page; an empty string for a page
     *                      with no extractable text (a scan, a picture).
     */
    public function pages(string $pdf): array;
}
