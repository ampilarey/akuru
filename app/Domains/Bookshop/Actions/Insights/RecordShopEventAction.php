<?php

namespace App\Domains\Bookshop\Actions\Insights;

use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Counts a step of a shop's funnel (BOOKSHOP_PLAN §6.8, slice B9e): a
 * visit to its page, a product viewed, an add to cart, a checkout started,
 * an order paid and what it sold. Daily counters only — nothing about the
 * visitor is written. A page view counts once per browser session, subject
 * and day, never for a crawler and never for the shop's own members. A
 * counter that cannot be written never breaks the page or the sale.
 */
class RecordShopEventAction
{
    private const SESSION_KEY = 'bookshop_seen';

    /** A page view, from a public shop page. */
    public function view(Request $request, string $vendorSlug, string $metric, string $subject): void
    {
        if (! config('bookshop.insights.enabled') || $this->isCrawler((string) $request->userAgent())) {
            return;
        }
        $vendorId = Vendor::query()->where('slug', $vendorSlug)->value('id');
        if ($vendorId === null) {
            return;
        }
        $userId = $request->user()?->id;
        if ($userId !== null && VendorMember::query()->where('vendor_id', $vendorId)->where('user_id', $userId)->exists()) {
            return;
        }
        if ($request->hasSession()) {
            $key = now()->toDateString().'|'.$vendorId.'|'.$metric.'|'.$subject;
            $seen = (array) $request->session()->get(self::SESSION_KEY, []);
            if (in_array($key, $seen, true)) {
                return;
            }
            $seen[] = $key;
            $request->session()->put(self::SESSION_KEY, array_slice($seen, -(int) config('bookshop.insights.session_cap', 300)));
        }
        $this->count((int) $vendorId, $metric, $subject);
    }

    /** A checkout started: one per shop's order in it. */
    public function checkout(iterable $orders): void
    {
        foreach ($orders as $order) {
            /** @var Order $order */
            $this->count((int) $order->vendor_id, 'checkout', '', 1, (float) $order->total);
        }
    }

    /** An order paid (or placed for cash on delivery), and each product in it. */
    public function paid(Order $order): void
    {
        $this->count((int) $order->vendor_id, 'order_paid', '', 1, (float) $order->total);
        foreach ($order->items()->get() as $item) {
            if ($item->product_id !== null) {
                $this->count((int) $order->vendor_id, 'product_sold', 'product:'.$item->product_id, (int) $item->quantity, (float) $item->line_total);
            }
        }
    }

    public function count(int $vendorId, string $metric, string $subject = '', int $by = 1, float $amount = 0.0): void
    {
        if (! config('bookshop.insights.enabled') || $by < 1) {
            return;
        }
        try {
            DB::table('shop_daily_stats')->upsert(
                [['vendor_id' => $vendorId, 'day' => now()->toDateString(), 'metric' => $metric, 'subject' => mb_substr($subject, 0, 100), 'count' => $by, 'amount' => round($amount, 2)]],
                ['vendor_id', 'day', 'metric', 'subject'],
                ['count' => DB::raw($this->column('count').' + '.$by), 'amount' => DB::raw($this->column('amount').' + '.number_format(round($amount, 2), 2, '.', ''))],
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function column(string $name): string
    {
        return DB::getQueryGrammar()->wrap($name);
    }

    public function isCrawler(string $userAgent): bool
    {
        return $userAgent === '' || preg_match((string) config('bookshop.insights.bot_pattern'), $userAgent) === 1;
    }
}
