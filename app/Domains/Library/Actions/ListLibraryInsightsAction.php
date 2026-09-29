<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryCategory;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\LibraryReadingEvent;
use App\Domains\Library\Models\LibraryReadingProgress;
use App\Domains\Library\Models\LibrarySearchLog;
use App\Domains\Library\Models\WriterProfile;
use Illuminate\Support\Carbon;

/**
 * B14 (LIBRARY_PLAN §29 "Admin", STATUS §5it): the office's view of the
 * Library over a period — who is reading, what is read most, which
 * categories and writers carry it, what is bought, and what people search
 * for and do not find. Aggregates only: nothing here names a reader.
 */
class ListLibraryInsightsAction
{
    public const PERIODS = ['week' => 7, 'month' => 30, 'quarter' => 90, 'all' => null];

    public const TOP = 10;

    /**
     * @return array<string, mixed>
     */
    public function execute(string $period = 'month'): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : 'month';
        $days = self::PERIODS[$period];
        $since = $days === null ? null : now()->subDays($days)->startOfDay();

        $allEvents = LibraryReadingEvent::query()->when($since, fn ($query) => $query->where('library_reading_events.occurred_at', '>=', $since));
        // R1: pages opened and files downloaded are counted apart.
        $events = (clone $allEvents)->where('library_reading_events.kind', 'page');
        $purchases = LibraryPurchase::query()->where('library_purchases.status', 'paid')->when($since, fn ($query) => $query->where('library_purchases.purchased_at', '>=', $since));
        $completions = LibraryReadingProgress::query()->whereNotNull('completed_at')->when($since, fn ($query) => $query->where('completed_at', '>=', $since));
        $searches = LibrarySearchLog::query()->when($since, fn ($query) => $query->where('created_at', '>=', $since));

        $headline = [
            'active_readers' => (int) (clone $allEvents)->distinct('user_id')->count('user_id'),
            'pages_opened' => (int) (clone $events)->count(),
            'downloads' => (int) (clone $allEvents)->where('library_reading_events.kind', 'download')->count(),
            'completions' => (int) (clone $completions)->count(),
            'purchases' => (int) (clone $purchases)->count(),
            'revenue' => (string) number_format((float) (clone $purchases)->sum('amount'), 2, '.', ''),
            'searches' => (int) (clone $searches)->count(),
        ];

        // Most read: pages opened per item, with distinct readers, completions and sales in the period.
        $read = (clone $events)
            ->selectRaw('library_item_id, COUNT(*) as pages, COUNT(DISTINCT user_id) as readers')
            ->groupBy('library_item_id')
            ->orderByDesc('pages')
            ->limit(self::TOP)
            ->get();
        $ids = $read->pluck('library_item_id')->all();
        $items = LibraryItem::query()->with(['category', 'writer'])->whereIn('id', $ids)->get()->keyBy('id');
        $completedByItem = (clone $completions)->whereIn('library_item_id', $ids)->selectRaw('library_item_id, COUNT(*) as n')->groupBy('library_item_id')->pluck('n', 'library_item_id');
        $soldByItem = (clone $purchases)->whereIn('library_item_id', $ids)->selectRaw('library_item_id, COUNT(*) as n')->groupBy('library_item_id')->pluck('n', 'library_item_id');
        $mostRead = $read->map(fn ($row) => [
            'library_item_id' => (int) $row->library_item_id,
            'title' => $items->get($row->library_item_id)?->title ?? '—',
            'slug' => $items->get($row->library_item_id)?->slug,
            'category' => $items->get($row->library_item_id)?->category?->name,
            'writer' => $items->get($row->library_item_id)?->writer?->display_name,
            'pages' => (int) $row->pages,
            'readers' => (int) $row->readers,
            'completions' => (int) ($completedByItem[$row->library_item_id] ?? 0),
            'purchases' => (int) ($soldByItem[$row->library_item_id] ?? 0),
        ])->values()->all();

        // Categories and writers, by pages opened and by money.
        $byCategory = (clone $events)
            ->join('library_items', 'library_items.id', '=', 'library_reading_events.library_item_id')
            ->selectRaw('library_items.library_category_id as category_id, COUNT(*) as pages, COUNT(DISTINCT library_reading_events.user_id) as readers')
            ->groupBy('library_items.library_category_id')
            ->orderByDesc('pages')
            ->limit(self::TOP)
            ->get();
        $categoryNames = LibraryCategory::query()->whereIn('id', $byCategory->pluck('category_id')->filter()->all())->pluck('name', 'id');
        $categories = $byCategory->map(fn ($row) => [
            'category' => $row->category_id ? ($categoryNames[$row->category_id] ?? '—') : 'Uncategorised',
            'pages' => (int) $row->pages,
            'readers' => (int) $row->readers,
        ])->values()->all();

        $byWriter = (clone $purchases)
            ->join('library_items', 'library_items.id', '=', 'library_purchases.library_item_id')
            ->whereNotNull('library_items.writer_id')
            ->selectRaw('library_items.writer_id as writer_id, COUNT(*) as sales, SUM(library_purchases.amount) as revenue')
            ->groupBy('library_items.writer_id')
            ->orderByDesc('revenue')
            ->limit(self::TOP)
            ->get();
        $writerNames = WriterProfile::query()->whereIn('id', $byWriter->pluck('writer_id')->all())->pluck('display_name', 'id');
        $writers = $byWriter->map(fn ($row) => [
            'writer' => $writerNames[$row->writer_id] ?? '—',
            'sales' => (int) $row->sales,
            'revenue' => (string) number_format((float) $row->revenue, 2, '.', ''),
        ])->values()->all();

        // What is looked for, and what is looked for and not found.
        $top = (clone $searches)->selectRaw('term, COUNT(*) as n, SUM(CASE WHEN hits = 0 THEN 1 ELSE 0 END) as misses')->groupBy('term')->orderByDesc('n')->orderBy('term')->limit(self::TOP)->get()
            ->map(fn ($row) => ['term' => $row->term, 'count' => (int) $row->n, 'misses' => (int) $row->misses])->values()->all();
        $empty = (clone $searches)->where('hits', 0)->selectRaw('term, COUNT(*) as n')->groupBy('term')->orderByDesc('n')->orderBy('term')->limit(self::TOP)->get()
            ->map(fn ($row) => ['term' => $row->term, 'count' => (int) $row->n])->values()->all();

        return [
            'period' => $period,
            'since' => $since?->toDateString(),
            'headline' => $headline,
            'most_read' => $mostRead,
            'categories' => $categories,
            'writers' => $writers,
            'searches' => ['top' => $top, 'empty' => $empty],
        ];
    }

    public static function since(string $period): ?Carbon
    {
        $days = self::PERIODS[$period] ?? self::PERIODS['month'];

        return $days === null ? null : now()->subDays($days)->startOfDay();
    }
}
