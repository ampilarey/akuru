<?php

namespace App\Domains\Library\Actions;

use App\Domains\HR\Actions\ReadPublicInstructorProfileAction;
use App\Domains\Library\Enums\LibraryDelivery;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;
use App\Domains\Library\Models\LibraryReadingProgress;
use App\Domains\Library\Models\WriterProfile;

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

        // R1 (D1): the file itself, where the author chose it and there is one.
        // Offered is what the page advertises; can_download is whether this
        // reader may have it now — the same access decision as reading.
        $delivery = $item->delivery instanceof LibraryDelivery ? $item->delivery : LibraryDelivery::Reader;
        $offersDownload = LibraryDelivery::offeredFor($item->content_type?->value)
            && $delivery->allowsDownload()
            && $item->pdf_media_file_id !== null;

        return app(ListLibraryItemsAction::class)->serialize($item) + $gate + [
            'delivery' => $delivery->value,
            'offers_download' => $offersDownload,
            'can_download' => $offersDownload && $canRead,
            // A download-only item is not read online — unless there is no
            // file to download, when the reader is all there is.
            'reads_online' => $delivery->allowsReader() || ! $offersDownload,
            'author_links' => $this->authorLinks($item),
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

    /**
     * R1: each named author, and where their name leads — a teacher to their
     * profile on the website (asked of HR, never read from its tables), a
     * writer to their author page, anyone else nowhere.
     *
     * @return list<array{name: string, kind: string, url: ?string}>
     */
    private function authorLinks(LibraryItem $item): array
    {
        $writers = WriterProfile::query()
            ->whereIn('user_id', $item->authors->pluck('user_id')->filter()->all())
            ->where('status', 'active')
            ->whereNotNull('slug')
            ->pluck('slug', 'user_id');
        $teachers = app(ReadPublicInstructorProfileAction::class);

        return $item->authors->map(function ($author) use ($writers, $teachers): array {
            if ($author->instructor_profile_id !== null) {
                $profile = $teachers->execute((int) $author->instructor_profile_id);

                return [
                    'name' => $profile['name'] ?? $author->name,
                    'kind' => 'teacher',
                    'url' => $profile !== null ? route('public.instructors.show', $profile['slug']) : null,
                ];
            }
            if ($author->user_id !== null && isset($writers[$author->user_id])) {
                return ['name' => $author->name, 'kind' => 'writer', 'url' => route('public.library.author', $writers[$author->user_id])];
            }

            return ['name' => $author->name, 'kind' => 'name', 'url' => null];
        })->values()->all();
    }
}
