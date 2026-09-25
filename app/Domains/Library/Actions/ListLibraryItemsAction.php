<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Media\Actions\ResolvePublicMediaUrlAction;

/**
 * L1 listing + basic search (LIBRARY_PLAN §28): published items for the
 * public surface, everything for admin. LIKE search over title, subtitle,
 * abstract and description. §8.2/§8.3 discovery (2026-09-25): free/paid,
 * language, price range, featured, and a sort — newest, most read, most
 * purchased, price either way, title.
 */
class ListLibraryItemsAction
{
    public const SORTS = ['newest', 'most_read', 'most_purchased', 'price_asc', 'price_desc', 'title'];

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function execute(array $filters = [], bool $publishedOnly = true): array
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTS, true) ? $filters['sort'] : 'newest';
        $priceMin = is_numeric($filters['price_min'] ?? null) ? (float) $filters['price_min'] : null;
        $priceMax = is_numeric($filters['price_max'] ?? null) ? (float) $filters['price_max'] : null;

        return LibraryItem::query()
            ->with(['category', 'tags', 'authors', 'writer'])
            ->withCount(['readers', 'paidPurchases'])
            ->when($publishedOnly, fn ($query) => $query->where('status', 'published'))
            ->when(($filters['access'] ?? null) === 'free', fn ($query) => $query->whereIn('access_type', ['free_public', 'free_login']))
            ->when(($filters['access'] ?? null) === 'paid', fn ($query) => $query->where('access_type', 'paid'))
            ->when($filters['language'] ?? null, fn ($query, $language) => $query->where('language', $language))
            ->when($priceMin !== null, fn ($query) => $query->where('price', '>=', $priceMin))
            ->when($priceMax !== null, fn ($query) => $query->where('price', '<=', $priceMax))
            ->when(filter_var($filters['featured'] ?? false, FILTER_VALIDATE_BOOL), fn ($query) => $query->where('featured', true))
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
            ->when($sort === 'most_read', fn ($query) => $query->orderByDesc('readers_count'))
            ->when($sort === 'most_purchased', fn ($query) => $query->orderByDesc('paid_purchases_count'))
            ->when($sort === 'price_asc', fn ($query) => $query->orderByRaw('COALESCE(price, 0) asc'))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByRaw('COALESCE(price, 0) desc'))
            ->when($sort === 'title', fn ($query) => $query->orderBy('title'))
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
            'featured' => (bool) $item->featured,
            'price' => $item->price !== null ? (string) $item->price : null,
            'currency' => $item->currency ?: 'MVR',
            'readers_count' => (int) ($item->readers_count ?? 0),
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
