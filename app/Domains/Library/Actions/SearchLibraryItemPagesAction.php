<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;

/**
 * B7 (LIBRARY_PLAN §9.1, STATUS §5iq): search inside one item, from the
 * reader.
 *
 * Scoped to what the reader may open — the same decision the page gate
 * makes (`ResolveLibraryAccessAction`): every page for a reader with access,
 * the first N for a previewer, nothing for anyone else. A hit is a page
 * number and a short plain-text snippet around the first match; the page
 * itself is still served (and watermarked, and logged) by the reader, so
 * search never becomes a way to read a book by the sentence.
 */
class SearchLibraryItemPagesAction
{
    public const MIN_TERM = 2;

    public const MAX_TERM = 100;

    public const LIMIT = 20;

    /**
     * The reader's `?q=`: null when nothing was asked, so the view shows no
     * result box at all.
     *
     * @return array{term: string, hits: list<array{page: int, snippet: string}>, readable_pages: int, truncated: bool}|null
     */
    public function forQuery(int $itemId, ?int $userId, ?string $term): ?array
    {
        if (trim((string) $term) === '') {
            return null;
        }
        $item = LibraryItem::query()->find($itemId);

        return $item === null ? null : $this->execute($item, $userId, (string) $term);
    }

    /**
     * @return array{term: string, hits: list<array{page: int, snippet: string}>, readable_pages: int, truncated: bool}
     */
    public function execute(LibraryItem $item, ?int $userId, string $term): array
    {
        $term = trim(mb_substr($term, 0, self::MAX_TERM));
        $gate = app(ResolveLibraryAccessAction::class)->execute($item, $userId);
        $total = LibraryItemPage::query()->where('library_item_id', $item->id)->count();
        $readable = $gate['can_read'] ? $total : min((int) ($gate['preview_pages'] ?? 0), $total);

        if (mb_strlen($term) < self::MIN_TERM || $readable === 0) {
            return ['term' => $term, 'hits' => [], 'readable_pages' => $readable, 'truncated' => false];
        }

        $pages = LibraryItemPage::query()
            ->where('library_item_id', $item->id)
            ->where('page_number', '<=', $readable)
            ->where('content', 'like', '%'.addcslashes($term, '%_\\').'%')
            ->orderBy('page_number')
            ->limit(self::LIMIT + 1)
            ->get(['page_number', 'content']);

        $hits = [];
        foreach ($pages->take(self::LIMIT) as $page) {
            $snippet = $this->snippet((string) $page->content, $term);
            // A match inside a tag or an attribute is not a match the reader can see.
            if ($snippet === null) {
                continue;
            }
            $hits[] = ['page' => (int) $page->page_number, 'snippet' => $snippet];
        }

        return ['term' => $term, 'hits' => $hits, 'readable_pages' => $readable, 'truncated' => $pages->count() > self::LIMIT];
    }

    /** Plain text around the first match, or null when the visible text has none. */
    private function snippet(string $html, string $term): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $at = mb_stripos($text, $term);
        if ($at === false) {
            return null;
        }
        $start = max(0, $at - 60);
        $slice = mb_substr($text, $start, mb_strlen($term) + 120);

        return ($start > 0 ? '…' : '').$slice.($start + mb_strlen($slice) < mb_strlen($text) ? '…' : '');
    }
}
