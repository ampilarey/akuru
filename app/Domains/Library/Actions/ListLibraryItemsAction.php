<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Media\Actions\ResolvePublicMediaUrlAction;

/**
 * L1 listing + basic search (LIBRARY_PLAN §28): published items for the
 * public surface, everything for admin. LIKE search over title, subtitle,
 * abstract and description.
 */
class ListLibraryItemsAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function execute(array $filters = [], bool $publishedOnly = true): array
    {
        return LibraryItem::query()
            ->with(['category', 'tags', 'authors', 'writer'])
            ->when($publishedOnly, fn ($query) => $query->where('status', 'published'))
            // L8: everything by one author, by their page's address.
            ->when($filters['author'] ?? null, fn ($query, $slug) => $query
                ->whereHas('writer', fn ($sub) => $sub->where('slug', $slug)->where('status', 'active')))
            ->when($filters['content_type'] ?? null, fn ($query, $type) => $query->where('content_type', $type))
            ->when($filters['category'] ?? null, fn ($query, $slug) => $query
                ->whereHas('category', fn ($sub) => $sub->where('slug', $slug)))
            ->when($filters['tag'] ?? null, fn ($query, $slug) => $query
                ->whereHas('tags', fn ($sub) => $sub->where('slug', $slug)))
            ->when(trim((string) ($filters['q'] ?? '')) !== '', function ($query) use ($filters) {
                $term = '%'.trim((string) $filters['q']).'%';
                $query->where(fn ($sub) => $sub
                    ->where('title', 'like', $term)
                    ->orWhere('subtitle', 'like', $term)
                    ->orWhere('abstract', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (LibraryItem $item): array => $this->serialize($item))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(LibraryItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'subtitle' => $item->subtitle,
            'slug' => $item->slug,
            'abstract' => $item->abstract,
            'description' => $item->description,
            'content_type' => $item->content_type?->value,
            'access_type' => $item->access_type?->value,
            'language' => $item->language,
            'cover_image' => $item->cover_image,
            // §36: the uploaded cover first; the office's typed URL as the
            // fallback; null shows the card's placeholder.
            'cover_url' => $this->coverUrl($item),
            'status' => $item->status?->value,
            'published_at' => $item->published_at?->toDateString(),
            'reading_time' => $item->reading_time,
            'page_count' => $item->page_count,
            'category' => $item->category ? [
                'id' => $item->category->id,
                'name' => $item->category->name,
                'slug' => $item->category->slug,
            ] : null,
            'tags' => $item->tags->map(fn ($tag) => ['name' => $tag->name, 'slug' => $tag->slug])->values()->all(),
            'authors' => $item->authors->map(fn ($author) => $author->name)->values()->all(),
            // L8: the writer's public page, when the item has a writer
            // account behind it (office-uploaded items may not).
            'writer' => $item->writer && $item->writer->status === 'active' && $item->writer->slug ? [
                'display_name' => $item->writer->display_name,
                'slug' => $item->writer->slug,
            ] : null,
            'has_pdf' => $item->pdf_media_file_id !== null,
        ];
    }

    public function coverUrl(LibraryItem $item): ?string
    {
        if ($item->cover_media_file_id !== null) {
            $url = app(ResolvePublicMediaUrlAction::class)->execute((int) $item->cover_media_file_id);
            if ($url !== null) {
                return $url;
            }
        }
        $typed = trim((string) $item->cover_image);

        return $typed !== '' && preg_match('#^(https?://|/)#', $typed) === 1 ? $typed : null;
    }
}
