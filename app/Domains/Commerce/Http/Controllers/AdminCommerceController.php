<?php

namespace App\Domains\Commerce\Http\Controllers;

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\IssueGiftCardAction;
use App\Domains\Commerce\Actions\ListGiftCardsAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\DiscountCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
            'gift_cards' => app(ListGiftCardsAction::class)->execute(),
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
        ]);
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
        ]);

        $result = app(IssueGiftCardAction::class)->execute($data + [
            'created_by' => (int) $request->user()->id,
        ]);

        // The one and only exposure of the plain code (§43.19).
        return back()->with('gift_card_code', $result['plain_code']);
    }

    public function creditWallet(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('commerce.manage'), 403);
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01|max:100000',
            'description' => 'nullable|string|max:500',
        ]);

        app(CreditWalletAction::class)->execute(
            (int) $data['user_id'],
            (float) $data['amount'],
            'admin',
            (int) $request->user()->id,
            $data['description'] ?? 'Manual credit',
        );

        return back()->with('success', 'Wallet credited.');
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
        ]);

        app(SaveDiscountCodeAction::class)->execute($data);

        return back()->with('success', 'Discount code saved.');
    }
}
