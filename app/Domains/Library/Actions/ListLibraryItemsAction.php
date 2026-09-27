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
    public const SORTS = ['newest', 'most_read', 'most_purchased', 'popular_week', 'popular_month', 'price_asc', 'price_desc', 'title'];

    /** B5 (§8.2): the three words a writer or the office may put on an item. */
    public const DIFFICULTIES = ['beginner', 'intermediate', 'advanced'];

    /**
     * B5 (§8.3): reading-time bands in minutes — short up to ten, medium to
     * thirty, long beyond. An item with no reading time is in no band.
     *
     * @var array<string, array{0: int|null, 1: int|null}>
     */
    public const READING_BANDS = ['short' => [null, 10], 'medium' => [11, 30], 'long' => [31, null]];

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function execute(array $filters = [], bool $publishedOnly = true): array
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTS, true) ? $filters['sort'] : 'newest';
        $priceMin = is_numeric($filters['price_min'] ?? null) ? (float) $filters['price_min'] : null;
        $priceMax = is_numeric($filters['price_max'] ?? null) ? (float) $filters['price_max'] : null;
        $band = self::READING_BANDS[$filters['reading'] ?? ''] ?? null;

        return LibraryItem::query()
            ->with(['category', 'tags', 'authors', 'writer'])
            ->withCount(['readers', 'paidPurchases'])
            // B5: "popular this week / month" counts pages opened in the window.
            ->when(str_starts_with($sort, 'popular_'), fn ($query) => $query
                ->withCount(['readingEvents as popular_count' => fn ($sub) => $sub
                    ->where('occurred_at', '>=', now()->subDays($sort === 'popular_week' ? 7 : 30))]))
            ->when($publishedOnly, fn ($query) => $query->where('status', 'published'))
            // B5 (§8.2–§8.4): difficulty, the reading-time band, and for
            // research: reviewed by a peer, or open to everyone.
            ->when(in_array($filters['difficulty'] ?? null, self::DIFFICULTIES, true), fn ($query) => $query->where('difficulty', $filters['difficulty']))
            ->when($band !== null, function ($query) use ($band) {
                $query->whereNotNull('reading_time');
                if ($band[0] !== null) {
                    $query->where('reading_time', '>=', $band[0]);
                }
                if ($band[1] !== null) {
                    $query->where('reading_time', '<=', $band[1]);
                }
            })
            ->when(filter_var($filters['peer_reviewed'] ?? false, FILTER_VALIDATE_BOOL), fn ($query) => $query
                ->where('content_type', 'research')
                ->whereHas('reviewAssignments', fn ($sub) => $sub->where('status', 'done')))
            ->when(filter_var($filters['open_access'] ?? false, FILTER_VALIDATE_BOOL), fn ($query) => $query
                ->where('content_type', 'research')
                ->where('access_type', 'free_public'))
            // Bookstore B11: one item by id, for a product that links to it.
            ->when(is_numeric($filters['id'] ?? null), fn ($query) => $query->whereKey((int) $filters['id']))
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
            ->when(str_starts_with($sort, 'popular_'), fn ($query) => $query->orderByDesc('popular_count'))
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
            'difficulty' => $item->difficulty,
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
