<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\OrderRefund;
use App\Domains\Bookshop\Models\ShopCreditAccount;
use App\Domains\Bookshop\Models\ShopCreditEntry;
use App\Domains\Identity\Actions\ResolveUserByIdentifierAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * COMMERCE_PARITY_PLAN P8c: credit accounts for schools (Bake & Grill's
 * CustomerCreditLedger). The office opens an account for a customer with a
 * limit and terms (days to pay); at checkout *Pay on account* is offered
 * while the account is active and its available credit covers the total,
 * and the checkout is paid at once with a **charge** on the ledger; the
 * office records the school's **payments**, and **deposits** paid ahead; a
 * refund of an order paid on account goes back as a **refund** entry. The
 * ledger is append-only (rule 12): owed = charges − payments − deposits −
 * refunds, never an edited number.
 */
class ShopCreditAction
{
    /** The office opens (or reopens) an account for an existing customer. */
    public function open(string $identifier, float $limit, int $termsDays, ?string $organisation, int $officeUserId, ?string $note = null): ShopCreditAccount
    {
        $user = app(ResolveUserByIdentifierAction::class)->execute(trim($identifier));
        if ($user === null) {
            throw ValidationException::withMessages(['identifier' => __('shop.error_credit_no_account')]);
        }
        if (ShopCreditAccount::query()->where('user_id', (int) $user->id)->exists()) {
            throw ValidationException::withMessages(['identifier' => __('shop.error_credit_exists')]);
        }

        return ShopCreditAccount::query()->create([
            'user_id' => (int) $user->id, 'organisation' => trim((string) $organisation) ?: null, 'credit_limit' => round($limit, 2),
            'terms_days' => $termsDays, 'status' => 'active', 'note' => trim((string) $note) ?: null, 'opened_by' => $officeUserId,
        ]);
    }

    /** The account's settings: limit, terms, status, name. Settings, not money, so they are edited. */
    public function update(int $accountId, float $limit, int $termsDays, string $status, ?string $organisation, ?string $note): ShopCreditAccount
    {
        if (! in_array($status, ShopCreditAccount::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('shop.error_credit_status')]);
        }
        $account = ShopCreditAccount::query()->findOrFail($accountId);
        $account->update(['credit_limit' => round($limit, 2), 'terms_days' => $termsDays, 'status' => $status, 'organisation' => trim((string) $organisation) ?: null, 'note' => trim((string) $note) ?: null]);

        return $account;
    }

    /**
     * What the customer may spend now, or null without an active account.
     *
     * @return array{limit: string, owed: string, available: string, terms_days: int, organisation: ?string}|null
     */
    public function standing(int $userId): ?array
    {
        $account = ShopCreditAccount::query()->where('user_id', $userId)->where('status', 'active')->first();

        return $account === null ? null : $this->figures($account);
    }

    /**
     * At checkout, inside its transaction: the account must be active and
     * cover the total; the charge goes on the ledger, once per checkout.
     */
    public function charge(int $userId, BookshopCheckout $checkout, float $total): ShopCreditEntry
    {
        $account = ShopCreditAccount::query()->where('user_id', $userId)->lockForUpdate()->first();
        if ($account === null || $account->status !== 'active') {
            throw ValidationException::withMessages(['payment_method' => __('shop.error_credit_none')]);
        }
        $available = (float) $this->figures($account)['available'];
        if (round($total, 2) > $available + 0.0001) {
            throw ValidationException::withMessages(['payment_method' => __('shop.error_credit_short', ['available' => number_format($available, 2)])]);
        }

        return ShopCreditEntry::query()->create([
            'shop_credit_account_id' => $account->id, 'kind' => 'charge', 'amount' => round($total, 2),
            'bookshop_checkout_id' => $checkout->id, 'reference' => $checkout->number, 'created_by' => $userId, 'created_at' => now(),
        ]);
    }

    /** A refund of an order paid on account goes back onto the account. */
    public function refund(int $userId, OrderRefund $refund, string $orderNumber, ?int $byUserId): ShopCreditEntry
    {
        $account = ShopCreditAccount::query()->where('user_id', $userId)->lockForUpdate()->firstOrFail();

        return ShopCreditEntry::query()->create([
            'shop_credit_account_id' => $account->id, 'kind' => 'refund', 'amount' => round((float) $refund->amount, 2),
            'order_refund_id' => $refund->id, 'reference' => $orderNumber, 'created_by' => $byUserId, 'created_at' => now(),
        ]);
    }

    /**
     * The office records money the school paid; the customer is told what is
     * still owed. A **deposit** — money paid ahead (Bake & Grill's deposits for
     * trade customers) — may go past what is owed and leaves the account in
     * credit, which adds to what it may spend; a plain payment may not, so a
     * mistyped amount cannot make credit by accident.
     */
    public function recordPayment(int $accountId, float $amount, string $reference, ?string $note, int $officeUserId, bool $deposit = false): ShopCreditEntry
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('shop.error_credit_amount')]);
        }
        $entry = DB::transaction(function () use ($accountId, $amount, $reference, $note, $officeUserId, $deposit) {
            $account = ShopCreditAccount::query()->lockForUpdate()->findOrFail($accountId);
            $owed = (float) $this->figures($account)['owed'];
            if (! $deposit && $amount > $owed + 0.0001) {
                throw ValidationException::withMessages(['amount' => __('shop.error_credit_overpaid', ['owed' => number_format($owed, 2)])]);
            }

            return ShopCreditEntry::query()->create([
                'shop_credit_account_id' => $account->id, 'kind' => $deposit ? 'deposit' : 'payment', 'amount' => $amount,
                'reference' => trim($reference), 'note' => trim((string) $note) ?: null, 'created_by' => $officeUserId, 'created_at' => now(),
            ]);
        });
        $account = ShopCreditAccount::query()->findOrFail($accountId);
        app(NotifyBookshopUserAction::class)->execute(
            (int) $account->user_id,
            __('shop.notice_credit_payment_title'),
            __('shop.notice_credit_payment_body', ['amount' => 'MVR '.number_format($amount, 2), 'owed' => 'MVR '.$this->figures($account)['owed']])
                .((float) $this->figures($account)['in_credit'] > 0 ? ' '.__('shop.notice_credit_in_credit', ['amount' => 'MVR '.$this->figures($account)['in_credit']]) : ''),
            '/my-orders',
        );

        return $entry;
    }

    /**
     * Every account for the office, most owed first.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $userModel = config('auth.providers.users.model');
        $accounts = ShopCreditAccount::query()->get();
        $people = $userModel::query()->whereIn('id', $accounts->pluck('user_id')->all())->get(['id', 'name', 'email', 'phone'])->keyBy('id');

        return $accounts->map(fn (ShopCreditAccount $a) => $this->figures($a) + [
            'id' => $a->id,
            'user_id' => (int) $a->user_id,
            'name' => $people[$a->user_id]->name ?? null,
            'email' => $people[$a->user_id]->email ?? null,
            'phone' => $people[$a->user_id]->phone ?? null,
            'status' => $a->status,
            'note' => $a->note,
            'opened_at' => $a->created_at?->format('Y-m-d'),
        ])->sortByDesc(fn ($r) => (float) $r['owed'])->values()->all();
    }

    /**
     * One account's ledger, oldest first, with the running balance.
     *
     * @return list<array{date: string, kind: string, reference: ?string, note: ?string, charge: ?string, credit: ?string, balance: string}>
     */
    public function statement(int $accountId): array
    {
        $balance = 0.0;

        return ShopCreditEntry::query()->where('shop_credit_account_id', $accountId)->orderBy('created_at')->orderBy('id')->get()
            ->map(function (ShopCreditEntry $e) use (&$balance) {
                $amount = (float) $e->amount;
                $balance += $e->kind === 'charge' ? $amount : -$amount;

                return [
                    'date' => $e->created_at?->format('Y-m-d H:i'), 'kind' => $e->kind, 'reference' => $e->reference, 'note' => $e->note,
                    'charge' => $e->kind === 'charge' ? number_format($amount, 2, '.', '') : null,
                    'credit' => $e->kind !== 'charge' ? number_format($amount, 2, '.', '') : null,
                    'balance' => number_format($balance, 2, '.', ''),
                ];
            })->values()->all();
    }

    public function accountIdFor(int $userId): ?int
    {
        $id = ShopCreditAccount::query()->where('user_id', $userId)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Owed, available and overdue. Overdue is what is still owed of the
     * charges older than the terms, after payments and refunds pay the
     * oldest first.
     *
     * @return array{limit: string, owed: string, in_credit: string, available: string, overdue: string, terms_days: int, organisation: ?string}
     */
    private function figures(ShopCreditAccount $account): array
    {
        $sums = ShopCreditEntry::query()->where('shop_credit_account_id', $account->id)->selectRaw('kind, sum(amount) as total')->groupBy('kind')->pluck('total', 'kind');
        $charges = (float) ($sums['charge'] ?? 0);
        $credits = (float) ($sums['payment'] ?? 0) + (float) ($sums['deposit'] ?? 0) + (float) ($sums['refund'] ?? 0);
        $net = round($charges - $credits, 2);
        $owed = max(0.0, $net);
        $old = (float) ShopCreditEntry::query()->where('shop_credit_account_id', $account->id)->where('kind', 'charge')
            ->where('created_at', '<', now()->subDays((int) $account->terms_days))->sum('amount');
        $overdue = max(0.0, round($old - $credits, 2));

        return [
            'limit' => number_format((float) $account->credit_limit, 2, '.', ''),
            'owed' => number_format($owed, 2, '.', ''),
            // A deposit ahead of any order is credit in hand: it adds to what may be spent.
            'in_credit' => number_format(max(0.0, -$net), 2, '.', ''),
            'available' => number_format(max(0.0, (float) $account->credit_limit - $net), 2, '.', ''),
            'overdue' => number_format(min($overdue, $owed), 2, '.', ''),
            'terms_days' => (int) $account->terms_days,
            'organisation' => $account->organisation,
        ];
    }
}
