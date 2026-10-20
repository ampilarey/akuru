<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Contracts\PageImageTextReader;
use App\Domains\Library\Contracts\PdfPageTextExtractor;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;
use App\Domains\Media\Actions\ReadPrivateMediaAction;
use App\Support\Contracts\PdfRasterizerInterface;
use Illuminate\Support\Facades\Log;

/**
 * BACKLOG B1 (STATUS §5tg): a scanned PDF's pages, read by text recognition.
 *
 * `SyncLibraryItemPagesAction` finds a PDF page with no text (a scan, a
 * picture) and, where recognition is switched on and the host can do it,
 * starts this. Each run reads a few pages — the host's queue worker lives
 * 55 seconds a minute (OWNER_ACTIONS 3), and one page at 300 dpi takes
 * Tesseract several seconds — writes them, and says where the next run
 * starts. A page that already had text keeps it; only the empty ones are
 * read. The PDF is the item's own `pdf_media_file_id`; if it changed since
 * recognition began, the run stops and the newer one wins.
 */
class RecogniseLibraryItemPagesAction
{
    /** Whether a scan can be read here: the switch, an OCR engine and a rasteriser. */
    public static function available(): bool
    {
        return (bool) config('library.ocr.enabled')
            && app(PageImageTextReader::class)->enabled()
            && app(PdfRasterizerInterface::class)->enabled();
    }

    /** Reads the pages from `$fromPage` on; returns the page the next run starts at, or null when done. */
    public function execute(int $itemId, int $mediaId, int $fromPage = 1): ?int
    {
        $item = LibraryItem::query()->find($itemId);
        if ($item === null || (int) $item->pdf_media_file_id !== $mediaId || (int) $item->ocr_media_file_id !== $mediaId || ! self::available()) {
            return null;
        }

        $file = app(ReadPrivateMediaAction::class)->execute($mediaId);
        if ($file === null) {
            return $this->finish($item);
        }
        $pdf = $file['contents'];

        try {
            $total = min(app(PdfRasterizerInterface::class)->pages($pdf), max(1, (int) config('library.ocr.max_pages', 400)));
        } catch (\Throwable $e) {
            Log::warning('Library PDF could not be counted for text recognition.', ['library_item_id' => $itemId, 'error' => $e->getMessage()]);

            return $this->finish($item);
        }

        try {
            $texts = app(PdfPageTextExtractor::class)->pages($pdf);
        } catch (\Throwable) {
            $texts = [];
        }

        if ($fromPage <= 1) {
            LibraryItemPage::query()->where('library_item_id', $item->id)->delete();
        }

        $last = min($total, $fromPage + max(1, (int) config('library.ocr.pages_per_run', 3)) - 1);
        for ($page = max(1, $fromPage); $page <= $last; $page++) {
            $text = trim((string) ($texts[$page - 1] ?? ''));
            if ($text === '') {
                try {
                    $png = app(PdfRasterizerInterface::class)->toPng($pdf, $page, (int) config('library.ocr.dpi', 300));
                    $text = app(PageImageTextReader::class)->text($png);
                } catch (\Throwable $e) {
                    Log::warning('Library PDF page could not be read by text recognition.', ['library_item_id' => $itemId, 'page' => $page, 'error' => $e->getMessage()]);
                    $text = '';
                }
            }
            LibraryItemPage::query()->updateOrCreate(
                ['library_item_id' => $item->id, 'page_number' => $page],
                ['content' => SyncLibraryItemPagesAction::pageHtml($text)],
            );
        }

        return $last < $total ? $last + 1 : $this->finish($item);
    }

    /** Done: a book with no text on any page keeps no pages, rather than pretending. */
    private function finish(LibraryItem $item): ?int
    {
        $pages = LibraryItemPage::query()->where('library_item_id', $item->id);
        if (! (clone $pages)->where('content', '!=', '')->exists()) {
            $pages->delete();
        }
        $item->page_count = LibraryItemPage::query()->where('library_item_id', $item->id)->count() ?: null;
        $item->pages_recognised_at = now();
        $item->save();

        return null;
    }
}
