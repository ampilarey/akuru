<?php

namespace App\Domains\Library\Console;

use App\Domains\Library\Actions\SyncLibraryItemPagesAction;
use App\Domains\Library\Models\LibraryItem;
use Illuminate\Console\Command;

/**
 * Rebuild reader pages from each item's body or PDF original.
 *
 * Exists for the items uploaded before PDFs made pages (2026-09-25): they
 * have a private PDF and no `library_item_pages` rows, and nothing else
 * re-reads a PDF that was saved before. By default touches only items that
 * have no pages; `--all` rebuilds every item, which is what to run after
 * the extractor improves.
 */
class SyncLibraryPagesCommand extends Command
{
    protected $signature = 'library:sync-pages {--all : Rebuild every item, not only those without pages} {--item= : One item id}';

    protected $description = 'Rebuild library reader pages from item bodies and PDF originals';

    public function handle(SyncLibraryItemPagesAction $sync): int
    {
        $query = LibraryItem::query()->orderBy('id');
        if ($this->option('item') !== null) {
            $query->whereKey((int) $this->option('item'));
        } elseif (! $this->option('all')) {
            $query->whereDoesntHave('pages')
                ->where(fn ($q) => $q->whereNotNull('pdf_media_file_id')->orWhereNotNull('body'));
        }

        $done = 0;
        $empty = 0;
        foreach ($query->cursor() as $item) {
            $count = $sync->execute($item);
            $done++;
            if ($count === 0) {
                $empty++;
                $this->warn(sprintf('#%d %s: no pages (no body, and the PDF has no readable text).', $item->id, $item->title));
            } else {
                $this->line(sprintf('#%d %s: %d pages from %s.', $item->id, $item->title, $count, $sync->source ?? 'nothing'));
            }
        }

        $this->info(sprintf('%d items synced, %d without pages.', $done, $empty));

        return self::SUCCESS;
    }
}
