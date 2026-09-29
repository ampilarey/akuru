<?php

namespace App\Domains\Bookshop\Actions\Money;

use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Enums\RefundStatus;
use App\Domains\Bookshop\Models\LoyaltyReward;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Settings\Actions\SetSettingAction;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bookstore rewards (STATUS §5lm, BOOKSHOP_PLAN §16 item 9): a share of
 * what a customer paid for the goods goes back into their Commerce wallet
 * once the order's return window has passed — so a return never has to
 * take a reward back.
 *
 * Off until the office turns it on. The office sets the share (percent),
 * the smallest order that earns and the most one order earns. Only orders
 * paid after it was last turned on earn, so switching it on never pays out
 * for the past. Delivery never earns; a discount and any refund come off
 * first. It is money into the wallet through `CreditWalletAction`, the one
 * way in (Rule 12): the ledger row and this one are written together, once.
 * A reward is wallet money, not a gift card, and the Bookstore sells no
 * gift cards, so nothing here ever earns on one.
 */
class LoyaltyRewardsAction
{
    /**
     * @return array{on: bool, percent: float, min_order: float, max_per_order: float, since: ?string}
     */
    public function settings(): array
    {
        $stored = app(SettingsRepositoryInterface::class)->get((string) config('bookshop.loyalty.setting_key'));
        $stored = is_string($stored) ? (json_decode($stored, true) ?: []) : (is_array($stored) ? $stored : []);
        $defaults = (array) config('bookshop.loyalty.defaults');

        return [
            'on' => (bool) ($stored['on'] ?? config('bookshop.loyalty.enabled_by_default', false)),
            'percent' => (float) ($stored['percent'] ?? $defaults['percent']),
            'min_order' => (float) ($stored['min_order'] ?? $defaults['min_order']),
            'max_per_order' => (float) ($stored['max_per_order'] ?? $defaults['max_per_order']),
            'since' => $stored['since'] ?? null,
        ];
    }

    /**
     * The office's switch and numbers. Turning it on (from off) starts the
     * clock: only orders paid from now earn.
     */
    public function save(bool $on, float $percent, float $minOrder, float $maxPerOrder): void
    {
        $current = $this->settings();
        $value = [
            'on' => $on,
            'percent' => round($percent, 2),
            'min_order' => round($minOrder, 2),
            'max_per_order' => round($maxPerOrder, 2),
            'since' => $on ? (($current['on'] && $current['since'] !== null) ? $current['since'] : now()->toIso8601String()) : null,
        ];
        app(SetSettingAction::class)->execute((string) config('bookshop.loyalty.setting_key'), $value, 'json', 'bookshop', 'Bookstore: rewards');
    }

    /** What an order earns at today's numbers — for the order page, before it is paid. */
    public function estimate(Order $order): float
    {
        $s = $this->settings();

        return $s['on'] ? $this->amountFor($this->base($order), $s) : 0.0;
    }

    /**
     * Pay every reward that is due. Run daily by `bookshop:award-rewards`;
     * returns how many were paid.
     */
    public function awardDue(): int
    {
        $s = $this->settings();
        if (! $s['on'] || $s['since'] === null) {
            return 0;
        }

        $paid = 0;
        Order::query()
            ->where('status', OrderStatus::Delivered->value)
            ->whereNotNull('user_id')
            ->whereNotNull('delivered_at')
            ->where('paid_at', '>=', Carbon::parse($s['since']))
            ->whereDoesntHave('loyaltyReward')
            ->with(['vendor', 'refunds'])
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($s, &$paid) {
                foreach ($orders as $order) {
                    if ($order->delivered_at->copy()->addDays($order->vendor->returnWindowDays())->isFuture()) {
                        continue;
                    }
                    $paid += $this->award($order, $s) ? 1 : 0;
                }
            });

        return $paid;
    }

    /**
     * The office's view: the numbers and the latest rewards.
     *
     * @return array<string, mixed>
     */
    public function report(int $limit = 20): array
    {
        return [
            'settings' => $this->settings(),
            'paid_total' => number_format((float) LoyaltyReward::query()->sum('amount'), 2, '.', ''),
            'paid_count' => LoyaltyReward::query()->count(),
            'latest' => LoyaltyReward::query()->with('order:id,number')->latest('id')->limit($limit)->get()
                ->map(fn (LoyaltyReward $r) => ['order' => $r->order?->number, 'amount' => (string) $r->amount, 'percent' => (string) $r->percent, 'at' => $r->created_at?->toDateString()])
                ->values()->all(),
        ];
    }

    /** @return list<list<string>> every reward, for the CSV */
    public function rows(): array
    {
        return LoyaltyReward::query()->with('order:id,number')->orderBy('id')->get()
            ->map(fn (LoyaltyReward $r) => [(string) $r->created_at?->toDateString(), (string) $r->order?->number, (string) $r->user_id, (string) $r->base_amount, (string) $r->percent, (string) $r->amount, $r->currency])
            ->values()->all();
    }

    /** @param  array{on: bool, percent: float, min_order: float, max_per_order: float, since: ?string}  $s */
    private function award(Order $order, array $s): bool
    {
        $base = $this->base($order);
        $amount = $this->amountFor($base, $s);
        if ($amount <= 0) {
            return false;
        }

        return DB::transaction(function () use ($order, $base, $amount, $s) {
            // One per order, even if two runs overlap: the row is locked in first.
            if (LoyaltyReward::query()->where('order_id', $order->id)->lockForUpdate()->exists()) {
                return false;
            }
            $reward = LoyaltyReward::query()->create([
                'user_id' => $order->user_id, 'order_id' => $order->id, 'base_amount' => $base,
                'percent' => $s['percent'], 'amount' => $amount, 'currency' => $order->currency,
            ]);
            $credit = app(CreditWalletAction::class)->execute((int) $order->user_id, $amount, 'loyalty_reward', $reward->id, 'Reward: Akuru Bookstore '.$order->number);
            $reward->forceFill(['wallet_transaction_id' => $credit->id])->save();

            return true;
        });
    }

    /** Goods paid for: the subtotal less its discount and anything refunded. Never delivery. */
    private function base(Order $order): float
    {
        $refunded = $order->relationLoaded('refunds')
            ? (float) $order->refunds->where('status', RefundStatus::Done)->sum('amount')
            : (float) $order->refunds()->where('status', RefundStatus::Done->value)->sum('amount');

        return max(0.0, round((float) $order->subtotal - (float) $order->discount - $refunded, 2));
    }

    /** @param  array{percent: float, min_order: float, max_per_order: float}  $s */
    private function amountFor(float $base, array $s): float
    {
        if ($base <= 0 || $base < $s['min_order'] || $s['percent'] <= 0) {
            return 0.0;
        }

        return round(min($base * $s['percent'] / 100, $s['max_per_order']), 2);
    }
}
