<?php

namespace App\Domains\Commerce\Http\Controllers;

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\DeactivateGiftCardAction;
use App\Domains\Commerce\Actions\IssueGiftCardAction;
use App\Domains\Commerce\Actions\ListGiftCardOrdersAction;
use App\Domains\Commerce\Actions\ListGiftCardsAction;
use App\Domains\Commerce\Actions\ResolveStoredValueLiabilityAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\DiscountCode;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * L4 admin: issue gift cards (plain code flashed ONCE — §43.19), manual
 * wallet credits (§37 "manual free access"/credit), discount codes.
 * commerce.manage gated.
 */
class AdminCommerceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('commerce.manage'), 403);

        return Inertia::render('Commerce/Admin', [
            // §13.7: what the Institute owes in stored value. An accounting
            // figure, not a nicety — every unspent gift card and every laari
            // of wallet credit is an obligation to hand over goods later.
            'liability' => app(ResolveStoredValueLiabilityAction::class)->execute(),
            'gift_cards' => app(ListGiftCardsAction::class)->execute(),
            // §7.7 / §41: the gift cards people bought, and where the code went.
            'gift_card_orders' => app(ListGiftCardOrdersAction::class)->execute(),
            'discount_codes' => DiscountCode::query()->orderByDesc('id')->limit(200)->get()
                ->map(fn (DiscountCode $code) => [
                    'id' => $code->id,
                    'code' => $code->code,
                    'name' => $code->name,
                    'discount_type' => $code->discount_type?->value,
                    'discount_value' => (string) $code->discount_value,
                    'usage_limit' => $code->usage_limit,
                    'per_user_limit' => $code->per_user_limit,
                    'status' => $code->status,
                ])->values()->all(),
            // CO1: the page's words, in the page's language.
            't' => Phrases::once('admin'),
        ]);
    }

    /** Every listing gets a CSV (conventions): the gift card purchases. */
    public function exportGiftCardOrders(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('commerce.manage'), 403);
        $rows = app(ListGiftCardOrdersAction::class)->execute(5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'buyer', 'buyer_email', 'amount', 'currency', 'recipient', 'status', 'delivered_via', 'delivered_to', 'gift_card_id', 'paid_at', 'created_at']);
            foreach ($rows as $row) {
                Csv::put($out, [
                    $row['id'], $row['buyer'], $row['buyer_email'], $row['amount'], $row['currency'], $row['recipient_name'],
                    $row['status'], $row['delivered_via'], $row['delivered_to'], $row['gift_card_id'], $row['paid_at'], $row['created_at'],
                ]);
            }
            fclose($out);
        }, 'gift-card-purchases.csv', ['Content-Type' => 'text/csv']);
    }

    public function issueGiftCard(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('commerce.manage'), 403);
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_email' => 'nullable|email|max:255',
            'message' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ], [], $this->fieldNames());

        $result = app(IssueGiftCardAction::class)->execute($data + [
            'created_by' => (int) $request->user()->id,
        ]);

        // The one and only exposure of the plain code (§43.19).
        return back()->with('gift_card_code', $result['plain_code']);
    }

    /** B10 (§15.2): take a card out of circulation, with the reason on the ledger. */
    public function deactivateGiftCard(Request $request, int $card): RedirectResponse
    {
        abort_unless($request->user()?->can('commerce.manage'), 403);
        $data = $request->validate(['reason' => 'required|string|max:500'], [], $this->fieldNames());

        app(DeactivateGiftCardAction::class)->execute($card, (int) $request->user()->id, $data['reason']);

        return back()->with('success', __('admin.commerce_flash_deactivated'));
    }

    public function creditWallet(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('commerce.manage'), 403);
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01|max:100000',
            'description' => 'nullable|string|max:500',
        ], [], $this->fieldNames());

        app(CreditWalletAction::class)->execute(
            (int) $data['user_id'],
            (float) $data['amount'],
            'admin',
            (int) $request->user()->id,
            // The ledger's own record, kept as it is written (rule 12).
            $data['description'] ?? 'Manual credit',
        );

        return back()->with('success', __('admin.commerce_flash_credited'));
    }

    public function storeDiscount(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('commerce.manage'), 403);
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'name' => 'nullable|string|max:255',
            'discount_type' => 'required|string|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'max_discount_amount' => 'nullable|numeric|min:0.01',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'nullable|integer|min:1',
            'minimum_order_amount' => 'nullable|numeric|min:0.01',
            'can_use_with_wallet' => 'nullable|boolean',
        ], [], $this->fieldNames());

        app(SaveDiscountCodeAction::class)->execute($data);

        return back()->with('success', __('admin.commerce_flash_discount_saved'));
    }

    /**
     * The fields the office posts, named for Laravel's own refusals in the
     * page's language: an account nobody has read *ޔޫޒަރ އައިޑީ* where it
     * read *user id* (slice CO1).
     *
     * @return array<string, string>
     */
    private function fieldNames(): array
    {
        return [
            'amount' => __('admin.commerce_attr_amount'),
            'recipient_name' => __('admin.commerce_attr_recipient_name'),
            'recipient_email' => __('admin.commerce_attr_recipient_email'),
            'message' => __('admin.commerce_attr_message'),
            'expires_at' => __('admin.commerce_attr_expires_at'),
            'reason' => __('admin.commerce_attr_reason'),
            'user_id' => __('admin.commerce_attr_user_id'),
            // The credit form's *Reason* box.
            'description' => __('admin.commerce_attr_reason'),
            'code' => __('admin.commerce_attr_code'),
            'name' => __('admin.commerce_attr_name'),
            'discount_type' => __('admin.commerce_attr_discount_type'),
            'discount_value' => __('admin.commerce_attr_discount_value'),
            'max_discount_amount' => __('admin.commerce_attr_max_discount_amount'),
            'usage_limit' => __('admin.commerce_attr_usage_limit'),
            'per_user_limit' => __('admin.commerce_attr_per_user_limit'),
            'minimum_order_amount' => __('admin.commerce_attr_minimum_order_amount'),
            'can_use_with_wallet' => __('admin.commerce_attr_can_use_with_wallet'),
        ];
    }
}
