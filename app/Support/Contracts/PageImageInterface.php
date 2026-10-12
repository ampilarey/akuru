<?php

namespace App\Support\Contracts;

/**
 * NAME_TAGS_PLAN §5.2 (slice NT0): a picture of a rendered HTML page — the
 * proof a customer sees of a print job, at screen resolution and
 * watermarked, never the print file. Bound to headless Chrome where one is
 * configured (`DOCUMENTS_CHROME_PATH`, as the PDF converter), disabled
 * elsewhere; the same engine lays out the HTML it photographs and the HTML
 * it prints, so the two agree.
 */
interface PageImageInterface
{
    /** Whether this host can take the picture at all. */
    public function enabled(): bool;

    /** PNG bytes of a complete HTML document laid out at this size in CSS pixels. Throws when it cannot. */
    public function fromHtml(string $html, int $widthPx, int $heightPx): string;
}
