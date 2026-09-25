<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Contracts\PdfPageTextExtractor;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use Illuminate\Support\Facades\Log;

/**
 * L2: the reader's pages, made from whichever of the two sources the item
 * has.
 *
 *  - A **body** is split on the explicit `<!-- pagebreak -->` marker the
 *    writer places (LIBRARY_PLAN §36's "secure HTML"). No markers = one page.
 *  - Otherwise the **PDF original** (private media, never served) is read
 *    page by page through `PdfPageTextExtractor`, and each page's text
 *    becomes a page of escaped paragraphs. Until 2026-09-25 a PDF-only item
 *    had no pages at all: the reader said "no reader pages yet" to a book
 *    the office had uploaded, and the shelf listed it as readable.
 *
 * The body wins when both exist, because it is the one a person edited.
 * Replaces the page set idempotently and keeps `page_count` honest — the
 * number of pages the reader will actually serve, which for a scanned PDF
 * with no extractable text is none.
 */
class SyncLibraryItemPagesAction
{
    public const PAGE_BREAK = '<!-- pagebreak -->';

    /** The source the pages came from on the last sync: 'body', 'pdf' or null. */
    public ?string $source = null;

    public function execute(LibraryItem $item): int
    {
        $this->source = null;
        $body = (string) ($item->body ?? '');
        $chunks = $body === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(self::PAGE_BREAK, $body)), fn ($chunk) => $chunk !== ''));

        if ($chunks !== []) {
            $this->source = 'body';
        } elseif ($item->pdf_media_file_id !== null) {
            $chunks = $this->pagesFromPdf((int) $item->pdf_media_file_id, (int) $item->id);
            $this->source = $chunks === [] ? null : 'pdf';
        }

        LibraryItemPage::query()->where('library_item_id', $item->id)->delete();
        foreach ($chunks as $index => $chunk) {
            LibraryItemPage::query()->create([
                'library_item_id' => $item->id,
                'page_number' => $index + 1,
                'content' => $chunk,
            ]);
        }

        $item->page_count = count($chunks) ?: null;
        $item->save();

        return count($chunks);
    }

    /**
     * One HTML page per PDF page. A page with no text (a picture, a scan)
     * stays in as an empty page so numbering matches the book; a PDF with no
     * text on any page yields nothing, and the item stays unreadable rather
     * than pretending.
     *
     * @return list<string>
     */
    private function pagesFromPdf(int $mediaId, int $itemId): array
    {
        // Scoped by the item's own `pdf_media_file_id`: the id comes from the
        // library item record, never from a request.
        $file = app(ReadPrivateMediaAction::class)->execute($mediaId);
        if ($file === null) {
            return [];
        }

        try {
            $texts = app(PdfPageTextExtractor::class)->pages($file['contents']);
        } catch (\Throwable $e) {
            Log::warning('Library PDF could not be read for pages.', ['library_item_id' => $itemId, 'error' => $e->getMessage()]);

            return [];
        }

        if (implode('', $texts) === '' || trim(implode('', $texts)) === '') {
            return [];
        }

        return array_map(fn (string $text) => $this->toHtml($text), $texts);
    }

    private function toHtml(string $text): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];
        $html = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            // `dir="auto"` lets a Dhivehi or Arabic paragraph run right-to-left
            // inside an English shell, and an English one the other way.
            $html[] = '<p dir="auto">'.nl2br(e($paragraph)).'</p>';
        }

        return implode("\n", $html);
    }
}
