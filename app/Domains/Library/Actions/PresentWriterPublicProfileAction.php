<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Media\Actions\ResolvePublicMediaUrlAction;

/**
 * L8 — the public author page (LIBRARY_PLAN §8.7): who the writer is and
 * everything of theirs the office has published, on one address.
 *
 * Only an active writer has a page, and only their **published** items are
 * listed — drafts, submissions and unpublished work stay inside `/write`.
 * Reading, buying and the free-reading gate happen on the item page, so
 * nothing here decides access; it is a shelf with a name on it.
 */
class PresentWriterPublicProfileAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $slug): ?array
    {
        $profile = WriterProfile::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->first();
        if ($profile === null) {
            return null;
        }

        $items = LibraryItem::query()
            ->with(['category', 'tags', 'authors'])
            ->where('writer_id', $profile->id)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        $serialize = app(ListLibraryItemsAction::class);
        $byType = [];
        foreach ($items as $item) {
            $byType[$item->content_type?->value ?? 'other'] = ($byType[$item->content_type?->value ?? 'other'] ?? 0) + 1;
        }

        // B6: the writer's own pins, in their order, and only while the pinned
        // work is still published — an unpublished pin simply leaves the shelf.
        $byId = $items->keyBy('id');
        $featured = collect((array) $profile->featured_item_ids)
            ->map(fn ($id) => $byId->get((int) $id))
            ->filter()
            ->values();

        $links = [];
        foreach (SaveWriterPublicProfileAction::LINK_KEYS as $key) {
            $url = (string) (($profile->social_links ?? [])[$key] ?? '');
            if ($url !== '') {
                $links[$key] = $url;
            }
        }

        return [
            'display_name' => $profile->display_name,
            'slug' => $profile->slug,
            'bio' => $profile->bio,
            'qualifications' => $profile->qualifications,
            'expertise' => $profile->expertise,
            'photo_url' => $profile->photo_media_file_id
                ? app(ResolvePublicMediaUrlAction::class)->execute((int) $profile->photo_media_file_id)
                : null,
            'writing_since' => $profile->approved_at?->format('Y'),
            'links' => $links,
            'totals' => ['published' => $items->count()] + $byType,
            'featured' => $featured->map(fn (LibraryItem $item) => $serialize->serialize($item))->values()->all(),
            'items' => $items->map(fn (LibraryItem $item) => $serialize->serialize($item))->values()->all(),
        ];
    }
}
