<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\OrderItem;
use Illuminate\Support\Carbon;

/**
 * NAME_TAGS_PLAN §11 NT7: the office's *print sales by paper and month* —
 * the print lines of paid orders (not cancelled), by the month they were
 * paid and the paper they were printed on: how many jobs, how many sheets,
 * what they came to. A refunded order is counted until it is cancelled, as
 * the Bookstore's other sales figures are.
 */
final class PrintSalesReport
{
    public const MONTHS = 12;

    /** @return list<array{month: string, paper: string, jobs: int, sheets: int, revenue: string}> newest month first */
    public static function byPaperAndMonth(int $months = self::MONTHS, string $locale = 'en'): array
    {
        $since = Carbon::now('Indian/Maldives')->startOfMonth()->subMonths(max(1, $months) - 1);
        $lines = OrderItem::query()->whereNotNull('print_job_id')
            ->whereHas('order', fn ($q) => $q->whereNotNull('paid_at')->where('paid_at', '>=', $since)->where('status', '!=', 'cancelled'))
            ->with(['order:id,paid_at', 'printJob' => fn ($q) => $q->withTrashed()->with('paper')])
            ->get(['id', 'order_id', 'print_job_id', 'quantity', 'line_total']);

        $rows = [];
        foreach ($lines as $line) {
            $month = $line->order->paid_at->copy()->timezone('Indian/Maldives')->format('Y-m');
            $paper = $line->printJob?->paper?->nameIn($locale) ?? (string) ($line->printJob?->approved_price_snapshot['paper']['name'] ?? '—');
            $key = $month.'|'.$paper;
            $rows[$key] ??= ['month' => $month, 'paper' => $paper, 'jobs' => 0, 'sheets' => 0, 'revenue' => 0.0];
            $rows[$key]['jobs']++;
            $rows[$key]['sheets'] += (int) $line->quantity;
            $rows[$key]['revenue'] += (float) $line->line_total;
        }
        uasort($rows, fn ($a, $b) => [$b['month'], $a['paper']] <=> [$a['month'], $b['paper']]);

        return array_values(array_map(fn (array $r) => ['revenue' => number_format($r['revenue'], 2, '.', '')] + $r, $rows));
    }
}
