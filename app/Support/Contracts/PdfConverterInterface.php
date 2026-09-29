<?php

namespace App\Support\Contracts;

/**
 * ADR-012 (amended 2026-09-29, STATUS §5lr): turning a rendered HTML
 * document into a PDF. Bound to headless Chrome only on a host where Chrome
 * is configured (`DOCUMENTS_CHROME_PATH`); everywhere else the disabled
 * binding says so, and the document stays HTML — never Dompdf, which cannot
 * shape Thaana or Arabic.
 */
interface PdfConverterInterface
{
    /** Whether this host can make a PDF at all. */
    public function enabled(): bool;

    /** The PDF's bytes for a complete HTML document. Throws when it cannot. */
    public function fromHtml(string $html): string;
}
