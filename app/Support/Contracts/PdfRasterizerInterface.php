<?php

namespace App\Support\Contracts;

/**
 * NAME_TAGS_PLAN §5.6 (slice NT5): a PDF page as a picture. Chrome cannot
 * photograph a PDF, so a customer's uploaded PDF is proofed through this —
 * bound to Ghostscript where `DOCUMENTS_GS_PATH` names one, disabled
 * elsewhere, where the upload form takes PNG and JPEG only and says so.
 */
interface PdfRasterizerInterface
{
    /** Whether this host can rasterise a PDF at all. */
    public function enabled(): bool;

    /** How many pages the PDF has. Throws when it cannot read it. */
    public function pages(string $pdf): int;

    /** PNG bytes of one page (1-based) at this resolution. Throws when it cannot. */
    public function toPng(string $pdf, int $page, int $dpi = 96): string;
}
