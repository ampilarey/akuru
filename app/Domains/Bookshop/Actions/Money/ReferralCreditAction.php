<?php

namespace App\Domains\Bookshop\Actions\Money;

use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Enums\RefundStatus;
use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\Referral;
use App\Domains\Bookshop\Models\ReferralCode;
use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Settings\Actions\SetSettingAction;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Referral credit (STATUS §5ln, BOOKSHOP_PLAN §16 item 9b).
 *
 * A signed-in customer has a share link, `/shop/r/{code}`. Someone who
 * opens it and then places their **first** Bookstore order is their
 * friend: a pending referral is kept against that checkout. Once every
 * order in it is settled — delivered and past its return window, or
 * cancelled — and the goods paid for (less discount and refunds, never
 * delivery) reach the office's smallest first order, both are credited
 * through `CreditWalletAction`, the one way into a wallet (Rule 12). A
 * first order that falls short, or is cancelled, voids the referral.
 *
 * Off until the office turns it on; while off no link is offered, no
 * referral is kept and none is paid. One per friend, never oneself. It is
 * wallet money, not a gift card, and the Bookstore sells no gift cards.
 */
class ReferralCreditAction
{
    public const SESSION_KEY = 'bookshop.ref';

    /**
     * @return array{on: bool, referrer_amount: float, friend_amount: float, min_order: float, since: ?string}
     */
    public function settings(): array
    {
        $stored = app(SettingsRepositoryInterface::class)->get((string) config('bookshop.referrals.setting_key'));
        $stored = is_string($stored) ? (json_decode($stored, true) ?: []) : (is_array($stored) ? $stored : []);
        $defaults = (array) config('bookshop.referrals.defaults');

        return [
            'on' => (bool) ($stored['on'] ?? config('bookshop.referrals.enabled_by_default', false)),
            'referrer_amount' => (float) ($stored['referrer_amount'] ?? $defaults['referrer_amount']),
            'friend_amount' => (float) ($stored['friend_amount'] ?? $defaults['friend_amount']),
            'min_order' => (float) ($stored['min_order'] ?? $defaults['min_order']),
            'since' => $stored['since'] ?? null,
        ];
    }

    /** The office's switch and amounts; turning it on (from off) starts the clock. */
    public function save(bool $on, float $referrerAmount, float $friendAmount, float $minOrder): void
    {
        $current = $this->settings();
        app(SetSettingAction::class)->execute((string) config('bookshop.referrals.setting_key'), [
            'on' => $on,
            'referrer_amount' => round($referrerAmount, 2),
            'friend_amount' => round($friendAmount, 2),
            'min_order' => round($minOrder, 2),
            'since' => $on ? (($current['on'] && $current['since'] !== null) ? $current['since'] : now()->toIso8601String()) : null,
        ], 'json', 'bookshop', 'Bookstore: referral credit');
    }

    /** A customer's own share code, made the first time. */
    public function codeFor(int $userId): string
    {
        $existing = ReferralCode::query()->where('user_id', $userId)->value('code');
        if ($existing !== null) {
            return (string) $existing;
        }
        do {
            $code = strtoupper(Str::random(8));
        } while (ReferralCode::query()->where('code', $code)->exists());

        return (string) ReferralCode::query()->firstOrCreate(['user_id' => $userId], ['code' => $code])->code;
    }

    /**
     * What My orders shows a customer while referrals are on: their link and
     * what it is worth, and how many friends it has brought.
     *
     * @return array{url: string, referrer_amount: string, friend_amount: string, min_order: string, paid: int, pending: int}|null
     */
    public function invite(int $userId): ?array
    {
        $s = $this->settings();
        if (! $s['on']) {
            return null;
        }

        return [
            'url' => route('public.shop.referral', $this->codeFor($userId)),
            'referrer_amount' => number_format($s['referrer_amount'], 2, '.', ''),
            'friend_amount' => number_format($s['friend_amount'], 2, '.', ''),
            'min_order' => number_format($s['min_order'], 2, '.', ''),
            'paid' => Referral::query()->where('referrer_user_id', $userId)->where('status', Referral::PAID)->count(),
            'pending' => Referral::query()->where('referrer_user_id', $userId)->where('status', Referral::PENDING)->count(),
        ];
    }

    /** Opening a share link: kept on this visit while referrals are on and the code is real. */
    public function remember(Session $session, string $code): bool
    {
        $code = strtoupper(trim($code));
        if (! $this->settings()['on'] || ! ReferralCode::query()->where('code', $code)->exists()) {
            return false;
        }
        $session->put(self::SESSION_KEY, $code);

        return true;
    }

    /**
     * For the checkout page: the friend's credit, when this visit came
     * through a link and this would be their first order.
     *
     * @return array{friend_amount: string, min_order: string}|null
     */
    public function offerFor(int $userId, Session $session): ?array
    {
        $referrer = $this->referrerFor($userId, $session);
        if ($referrer === null) {
            return null;
        }
        $s = $this->settings();

        return ['friend_amount' => number_format($s['friend_amount'], 2, '.', ''), 'min_order' => number_format($s['min_order'], 2, '.', '')];
    }

    /**
     * At checkout: a friend's first order keeps its referral. A friend whose
     * earlier try went unpaid and tries again keeps theirs, now on the new
     * checkout — the one that can actually be delivered.
     */
    public function attach(int $userId, BookshopCheckout $checkout, Session $session): ?Referral
    {
        if (! $this->settings()['on']) {
            return null;
        }
        $pending = Referral::query()->where('referred_user_id', $userId)->where('status', Referral::PENDING)->first();
        if ($pending !== null) {
            $session->forget(self::SESSION_KEY);
            if ($this->orderedBefore($userId, $checkout->id)) {
                return $pending;
            }
            $pending->forceFill(['bookshop_checkout_id' => $checkout->id])->save();

            return $pending;
        }
        $referrer = $this->referrerFor($userId, $session, $checkout->id);
        $code = (string) $session->pull(self::SESSION_KEY, '');
        if ($referrer === null) {
            return null;
        }

        return Referral::query()->firstOrCreate(['referred_user_id' => $userId], [
            'referrer_user_id' => $referrer, 'bookshop_checkout_id' => $checkout->id, 'code' => $code, 'status' => Referral::PENDING, 'currency' => $checkout->currency ?? 'MVR',
        ]);
    }

    /**
     * Settle every pending referral whose first order is settled. Run daily
     * by `bookshop:award-rewards`; returns how many were paid.
     */
    public function awardDue(): int
    {
        $s = $this->settings();
        if (! $s['on'] || $s['since'] === null) {
            return 0;
        }

        $paid = 0;
        Referral::query()
            ->where('status', Referral::PENDING)
            ->where('created_at', '>=', Carbon::parse($s['since']))
            ->with(['checkout.orders.vendor', 'checkout.orders.refunds'])
            ->orderBy('id')
            ->chunkById(200, function ($referrals) use ($s, &$paid) {
                foreach ($referrals as $referral) {
                    $paid += $this->settle($referral, $s) ? 1 : 0;
                }
            });

        return $paid;
    }

    /**
     * The office's view.
     *
     * @return array<string, mixed>
     */
    public function report(int $limit = 20): array
    {
        return [
            'settings' => $this->settings(),
            'paid_count' => Referral::query()->where('status', Referral::PAID)->count(),
            'pending_count' => Referral::query()->where('status', Referral::PENDING)->count(),
            'paid_total' => number_format((float) Referral::query()->where('status', Referral::PAID)->sum(DB::raw('referrer_amount + friend_amount')), 2, '.', ''),
            'latest' => Referral::query()->with('checkout:id,number')->latest('id')->limit($limit)->get()
                ->map(fn (Referral $r) => ['checkout' => $r->checkout?->number, 'status' => $r->status, 'referrer_amount' => $r->referrer_amount !== null ? (string) $r->referrer_amount : null, 'friend_amount' => $r->friend_amount !== null ? (string) $r->friend_amount : null, 'at' => $r->created_at?->toDateString()])
                ->values()->all(),
        ];
    }

    /** @return list<list<string>> every referral, for the CSV */
    public function rows(): array
    {
        return Referral::query()->with('checkout:id,number')->orderBy('id')->get()
            ->map(fn (Referral $r) => [
                (string) $r->created_at?->toDateString(), (string) $r->checkout?->number, (string) $r->referrer_user_id, (string) $r->referred_user_id,
                $r->status, (string) ($r->base_amount ?? ''), (string) ($r->referrer_amount ?? ''), (string) ($r->friend_amount ?? ''), $r->currency,
            ])->values()->all();
    }

    /** Who referred this person, if this visit came through a link and they have never ordered. */
    private function referrerFor(int $userId, Session $session, ?int $exceptCheckoutId = null): ?int
    {
        $code = (string) $session->get(self::SESSION_KEY, '');
        if ($code === '' || ! $this->settings()['on']) {
            return null;
        }
        $referrer = ReferralCode::query()->where('code', $code)->value('user_id');
        if ($referrer === null || (int) $referrer === $userId || Referral::query()->where('referred_user_id', $userId)->exists()) {
            return null;
        }

        return $this->orderedBefore($userId, $exceptCheckoutId) ? null : (int) $referrer;
    }

    /** Any order of theirs, other than this checkout's, that was paid or is cash due: not a first order. */
    private function orderedBefore(int $userId, ?int $exceptCheckoutId): bool
    {
        return Order::query()->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNotNull('paid_at')->orWhereIn('status', [OrderStatus::CashDue->value, OrderStatus::Delivered->value]))
            ->when($exceptCheckoutId !== null, fn ($q) => $q->where('bookshop_checkout_id', '!=', $exceptCheckoutId))
            ->exists();
    }

    /** @param  array{referrer_amount: float, friend_amount: float, min_order: float}  $s */
    private function settle(Referral $referral, array $s): bool
    {
        $orders = $referral->checkout?->orders ?? collect();
        $final = [OrderStatus::Cancelled, OrderStatus::Expired];
        foreach ($orders as $order) {
            $settled = in_array($order->status, $final, true)
                || ($order->status === OrderStatus::Delivered && $order->delivered_at !== null && ! $order->delivered_at->copy()->addDays($order->vendor->returnWindowDays())->isFuture());
            if (! $settled) {
                return false;
            }
        }
        $base = round($orders->where('status', OrderStatus::Delivered)->sum(fn (Order $o) => max(0.0, (float) $o->subtotal - (float) $o->discount
            - (float) $o->refunds->where('status', RefundStatus::Done)->sum('amount'))), 2);

        return DB::transaction(function () use ($referral, $s, $base) {
            $locked = Referral::query()->whereKey($referral->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== Referral::PENDING) {
                return false;
            }
            if ($base <= 0 || $base < $s['min_order']) {
                $locked->forceFill(['status' => Referral::VOID, 'base_amount' => $base, 'decided_at' => now()])->save();

                return false;
            }
            $number = $locked->checkout?->number;
            $toReferrer = app(CreditWalletAction::class)->execute((int) $locked->referrer_user_id, $s['referrer_amount'], 'referral', $locked->id, 'Referral: a friend\'s first Akuru Bookstore order '.$number);
            $toFriend = app(CreditWalletAction::class)->execute((int) $locked->referred_user_id, $s['friend_amount'], 'referral', $locked->id, 'Referral: your first Akuru Bookstore order '.$number);
            $locked->forceFill([
                'status' => Referral::PAID, 'base_amount' => $base, 'referrer_amount' => $s['referrer_amount'], 'friend_amount' => $s['friend_amount'],
                'referrer_wallet_transaction_id' => $toReferrer->id, 'friend_wallet_transaction_id' => $toFriend->id, 'decided_at' => now(),
            ])->save();

            return true;
        });
    }
}
