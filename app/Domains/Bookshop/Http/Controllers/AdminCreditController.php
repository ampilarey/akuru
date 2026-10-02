<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\ShopCreditAction;
use App\Domains\Bookshop\Models\ShopCreditAccount;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * COMMERCE_PARITY_PLAN P8c: the office's credit accounts — open one for a
 * school, set its limit and terms, record what it paid, read its statement.
 */
class AdminCreditController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $credit = app(ShopCreditAction::class);
        $open = (int) $request->query('account', 0);

        return Inertia::render('Bookshop/Credit', [
            't' => Phrases::once('shop'),
            'accounts' => $credit->list(),
            'statement' => $open > 0 ? ['account_id' => $open, 'entries' => $credit->statement($open)] : null,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'identifier' => 'required|string|max:120', 'organisation' => 'nullable|string|max:160',
            'credit_limit' => 'required|numeric|min:0|max:10000000', 'terms_days' => 'required|integer|min:1|max:365', 'note' => 'nullable|string|max:1000',
        ]);
        app(ShopCreditAction::class)->open($data['identifier'], (float) $data['credit_limit'], (int) $data['terms_days'], $data['organisation'] ?? null, (int) $request->user()->id, $data['note'] ?? null);

        return back()->with('success', __('shop.credit_opened'));
    }

    public function update(Request $request, int $account): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'organisation' => 'nullable|string|max:160', 'credit_limit' => 'required|numeric|min:0|max:10000000',
            'terms_days' => 'required|integer|min:1|max:365', 'status' => 'required|string|in:'.implode(',', ShopCreditAccount::STATUSES), 'note' => 'nullable|string|max:1000',
        ]);
        app(ShopCreditAction::class)->update($account, (float) $data['credit_limit'], (int) $data['terms_days'], $data['status'], $data['organisation'] ?? null, $data['note'] ?? null);

        return back()->with('success', __('shop.credit_saved'));
    }

    public function payment(Request $request, int $account): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['amount' => 'required|numeric|min:0.01|max:10000000', 'reference' => 'required|string|max:120', 'note' => 'nullable|string|max:500', 'deposit' => 'nullable|boolean']);
        app(ShopCreditAction::class)->recordPayment($account, (float) $data['amount'], $data['reference'], $data['note'] ?? null, (int) $request->user()->id, (bool) ($data['deposit'] ?? false));

        return back()->with('success', __('shop.credit_payment_recorded'));
    }

    /** Every listing gets a CSV (conventions). */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(ShopCreditAction::class)->list();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['customer', 'email', 'phone', 'organisation', 'status', 'limit', 'owed', 'in_credit', 'available', 'overdue', 'terms_days', 'opened']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['name'], $r['email'], $r['phone'], $r['organisation'], $r['status'], $r['limit'], $r['owed'], $r['in_credit'], $r['available'], $r['overdue'], $r['terms_days'], $r['opened_at']]);
            }
            fclose($out);
        }, 'bookstore-credit-accounts.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function statement(Request $request, int $account): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        ShopCreditAccount::query()->findOrFail($account);

        return self::statementCsv(app(ShopCreditAction::class)->statement($account), 'credit-statement-'.$account.'.csv');
    }

    /** The customer's own statement, from My orders. */
    public function mine(Request $request): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);
        $credit = app(ShopCreditAction::class);
        $account = $credit->accountIdFor((int) $request->user()->id);
        abort_if($account === null, 404);

        return self::statementCsv($credit->statement($account), 'my-credit-statement.csv');
    }

    /** @param  list<array<string, mixed>>  $entries */
    private static function statementCsv(array $entries, string $name): StreamedResponse
    {
        return response()->streamDownload(function () use ($entries): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['date', 'kind', 'reference', 'note', 'charge', 'paid_or_refunded', 'balance']);
            foreach ($entries as $e) {
                Csv::put($out, [$e['date'], $e['kind'], $e['reference'], $e['note'], $e['charge'], $e['credit'], $e['balance']]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
