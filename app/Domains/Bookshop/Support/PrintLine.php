<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Enums\PrintJobStatus;
use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\PrintJob;
use App\Domains\Bookshop\Models\Product;
use Illuminate\Support\Carbon;

/**
 * NAME_TAGS_PLAN §3.8, decisions D11 and D15 (NT4): what a print line in the
 * cart costs and whether it may be bought. Its quantity is the job's
 * sheets; its price is the price approved with the proof, held for seven
 * days — never the product's price and never today's price list (risk R7).
 * The line's total is the approved total itself, so a unit price rounded
 * to laari never moves the sum.
 */
final class PrintLine
{
    /**
     * Why the line cannot be bought now, as a `print.*` phrase key — or null
     * where it can. A line priced by a school's quote (PP6b) is held by the
     * quote, not by the approval's seven days.
     */
    public static function blocker(?PrintJob $job, int $quantity, ?int $userId = null, bool $quoted = false): ?string
    {
        if ($job === null || $job->trashed()) {
            return 'print.error_cart_job_gone';
        }
        if ($job->status !== PrintJobStatus::Approved || empty($job->approved_price_snapshot)) {
            return 'print.error_cart_reapprove';
        }
        if ($userId !== null && (int) $job->user_id !== $userId) {
            return 'print.error_cart_job_gone';
        }
        if (! $quoted && ! self::held($job)) {
            return 'print.error_cart_price_lapsed';
        }
        if ($quantity !== (int) $job->sheets) {
            return 'print.error_cart_quantity';
        }

        return null;
    }

    public static function held(PrintJob $job): bool
    {
        $until = $job->approved_price_snapshot['held_until'] ?? null;

        return is_string($until) && Carbon::parse($until)->isFuture();
    }

    public static function heldUntil(PrintJob $job): ?Carbon
    {
        $until = $job->approved_price_snapshot['held_until'] ?? null;

        return is_string($until) ? Carbon::parse($until) : null;
    }

    public static function total(PrintJob $job): float
    {
        return round((float) ($job->approved_price_snapshot['total'] ?? 0), 2);
    }

    public static function unit(PrintJob $job): float
    {
        return round(self::total($job) / max(1, (int) $job->sheets), 2);
    }

    /**
     * PP6b: a print line from an accepted school quote pays the quoted price
     * while the quote holds — the quote's own line for this very job.
     */
    public static function quoted(CartItem $item, Product $product): ?float
    {
        $price = CartPrice::quoted($item, $product, null);
        if ($price === null || $item->print_job_id === null) {
            return null;
        }

        return (int) ($item->quoteItem()->value('print_job_id') ?? 0) === (int) $item->print_job_id ? $price : null;
    }

    public static function job(CartItem $item): ?PrintJob
    {
        return $item->print_job_id === null ? null : PrintJob::withTrashed()->find($item->print_job_id);
    }
}
