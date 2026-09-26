<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ShopDailyStat;
use App\Domains\Bookshop\Models\Vendor;
use Carbon\CarbonImmutable;

/**
 * Reads the funnel counters (slice B9e) for one shop or every shop over the
 * last N days: the steps with the rate from each step to the next, the
 * days, the products most looked at and what they sold, and the shop's
 * pages. One reader for the shop's page, the office's and the CSVs.
 */
final class InsightsReport
{
    public static function days(int $days): int
    {
        $ranges = array_map('intval', (array) config('bookshop.insights.ranges', [7, 30, 90]));

        return in_array($days, $ranges, true) ? $days : $ranges[1] ?? 30;
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(?int $vendorId, int $days): array
    {
        $days = self::days($days);
        $to = CarbonImmutable::today();
        $from = $to->subDays($days - 1);
        $rows = ShopDailyStat::query()->when($vendorId !== null, fn ($q) => $q->where('vendor_id', $vendorId))
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])->get();

        $totals = [];
        foreach (ShopDailyStat::FUNNEL as $metric) {
            $totals[$metric] = (int) $rows->where('metric', $metric)->sum('count');
        }
        $funnel = [];
        $previous = null;
        foreach (ShopDailyStat::FUNNEL as $metric) {
            $funnel[] = ['metric' => $metric, 'count' => $totals[$metric], 'rate' => $previous ? round($totals[$metric] * 100 / $previous, 1) : null];
            $previous = $totals[$metric];
        }

        $daily = [];
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $onDay = $rows->filter(fn (ShopDailyStat $r) => $r->day->toDateString() === $day->toDateString());
            $line = ['day' => $day->toDateString()];
            foreach (ShopDailyStat::FUNNEL as $metric) {
                $line[$metric] = (int) $onDay->where('metric', $metric)->sum('count');
            }
            $line['revenue'] = number_format((float) $onDay->where('metric', 'order_paid')->sum('amount'), 2, '.', '');
            $daily[] = $line;
        }

        return [
            'days' => $days,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'funnel' => $funnel,
            'revenue' => number_format((float) $rows->where('metric', 'order_paid')->sum('amount'), 2, '.', ''),
            'conversion' => $totals['product_view'] > 0 ? round($totals['order_paid'] * 100 / $totals['product_view'], 1) : null,
            'daily' => $daily,
            'top_products' => self::topProducts($rows),
            'top_pages' => $rows->where('metric', 'shop_view')->groupBy('subject')
                ->map(fn ($group, $subject) => ['page' => (string) $subject, 'views' => (int) $group->sum('count')])
                ->sortByDesc('views')->take((int) config('bookshop.insights.top', 10))->values()->all(),
        ];
    }

    /**
     * Every shop's funnel side by side, for the office.
     *
     * @return list<array<string, mixed>>
     */
    public static function byShop(int $days): array
    {
        $days = self::days($days);
        $from = CarbonImmutable::today()->subDays($days - 1)->toDateString();
        $sums = ShopDailyStat::query()->where('day', '>=', $from)->whereIn('metric', ShopDailyStat::FUNNEL)
            ->selectRaw('vendor_id, metric, sum(count) as n, sum(amount) as amount')->groupBy('vendor_id', 'metric')->get()->groupBy('vendor_id');

        return Vendor::query()->orderBy('name')->get(['id', 'name', 'slug'])->map(function (Vendor $vendor) use ($sums) {
            $mine = $sums->get($vendor->id, collect());
            $row = ['vendor' => $vendor->name, 'slug' => $vendor->slug];
            foreach (ShopDailyStat::FUNNEL as $metric) {
                $row[$metric] = (int) ($mine->firstWhere('metric', $metric)?->n ?? 0);
            }
            $row['revenue'] = number_format((float) ($mine->firstWhere('metric', 'order_paid')?->amount ?? 0), 2, '.', '');
            $row['conversion'] = $row['product_view'] > 0 ? round($row['order_paid'] * 100 / $row['product_view'], 1) : null;

            return $row;
        })->values()->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ShopDailyStat>  $rows
     * @return list<array<string, mixed>>
     */
    private static function topProducts($rows): array
    {
        $byProduct = $rows->filter(fn (ShopDailyStat $r) => str_starts_with($r->subject, 'product:'))->groupBy('subject');
        $ids = $byProduct->keys()->map(fn ($s) => (int) substr((string) $s, 8))->all();
        $titles = Product::query()->whereIn('id', $ids)->pluck('title', 'id');

        return $byProduct->map(function ($group, $subject) use ($titles) {
            $id = (int) substr((string) $subject, 8);

            return [
                'id' => $id,
                'title' => $titles->get($id) ?? '#'.$id,
                'views' => (int) $group->where('metric', 'product_view')->sum('count'),
                'cart_adds' => (int) $group->where('metric', 'cart_add')->sum('count'),
                'sold' => (int) $group->where('metric', 'product_sold')->sum('count'),
                'sales' => number_format((float) $group->where('metric', 'product_sold')->sum('amount'), 2, '.', ''),
            ];
        })->sortByDesc(fn ($p) => [$p['views'], $p['sold']])->take((int) config('bookshop.insights.top', 10))->values()->all();
    }
}
