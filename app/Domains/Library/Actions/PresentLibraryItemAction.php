<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;
use App\Domains\Library\Models\LibraryReadingProgress;

/**
 * L1 detail + free-reading gate (LIBRARY_PLAN §6): free_public reads
 * without login; free_login requires a user; every other access type is
 * locked until its phase (L3 payments, course/manual grants). The body is
 * withheld — never sent and hidden client-side — when the gate fails.
 */
class PresentLibraryItemAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $slug, ?int $userId = null, bool $publishedOnly = true): ?array
    {
        $item = LibraryItem::query()
            ->with(['category', 'tags', 'authors', 'writer'])
            ->where('slug', $slug)
            ->when($publishedOnly, fn ($query) => $query->where('status', 'published'))
            ->first();
        if ($item === null) {
            return null;
        }

        $gate = app(ResolveLibraryAccessAction::class)->execute($item, $userId);
        $canRead = $gate['can_read'];

        // L2: reader entry point — total pages and where this reader left off.
        $totalPages = LibraryItemPage::query()->where('library_item_id', $item->id)->count();
        $continuePage = null;
        if ($userId !== null && $totalPages > 0) {
            $continuePage = LibraryReadingProgress::query()
                ->where('user_id', $userId)
                ->where('library_item_id', $item->id)
                ->value('current_page');
        }

        $declarations = is_array($item->declarations) ? $item->declarations : [];
        $authorNames = $item->authors->pluck('name')->all();
        if ($authorNames === [] && $item->writer?->display_name) {
            $authorNames = [$item->writer->display_name];
        }

        return app(ListLibraryItemsAction::class)->serialize($item) + $gate + [
            'body' => $canRead ? $item->body : null,
            // L7: citations are part of the scholarly record — always public.
            'citations' => $item->citations,
            // §8.8: the table of contents (books), the research's affiliation
            // and field, the copyright notice, and the AI-use declaration
            // where the author made one.
            'toc' => $item->toc,
            'affiliation' => $item->affiliation,
            'research_field' => $item->research_field,
            'ai_use_declared' => ! empty($declarations['ai_use']),
            'copyright_notice' => '© '.($item->published_at?->format('Y') ?? now()->format('Y')).' '.($authorNames !== [] ? implode(', ', $authorNames) : config('app.name')),
            'price' => $item->price !== null ? (string) $item->price : null,
            'currency' => $item->currency,
            'total_pages' => $totalPages,
            'continue_page' => $continuePage !== null ? (int) $continuePage : null,
        ];
    }
}
