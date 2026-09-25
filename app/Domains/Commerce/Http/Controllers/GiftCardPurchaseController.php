<?php

namespace App\Domains\Commerce\Http\Controllers;

use App\Domains\Commerce\Actions\StartGiftCardPurchaseAction;
use App\Domains\Commerce\Models\GiftCardOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * §15.3 public gift card purchase. The page is public — a gift is often the
 * first thing a visitor buys — and buying needs a sign-in, because the
 * payment row and the order belong to someone. The return page only
 * DISPLAYS state; the card is issued by the webhook listener (§43.5).
 */
class GiftCardPurchaseController extends Controller
{
    public function index(Request $request)
    {
        return view('public.commerce.gift-cards', [
            'presets' => (array) config('library.gift_cards.presets', [100, 250, 500, 1000]),
            'min' => (int) config('library.gift_cards.min', 50),
            'max' => (int) config('library.gift_cards.max', 5000),
            'signedIn' => $request->user() !== null,
        ]);
    }

    public function purchase(Request $request)
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'amount' => 'required|numeric',
            'recipient_name' => 'required|string|max:120',
            'recipient_email' => 'nullable|email|max:190',
            'recipient_mobile' => 'nullable|string|max:20',
            'message' => 'nullable|string|max:500',
        ]);

        $result = app(StartGiftCardPurchaseAction::class)->execute(
            (int) $request->user()->id,
            $data,
            route('public.gift-cards.return'),
        );

        if ($result['redirect_url'] !== null) {
            return redirect()->away($result['redirect_url']);
        }

        return redirect()
            ->route('public.gift-cards.index')
            ->with('error', $result['error'] ?? 'Payment could not be started.');
    }

    public function paymentReturn(Request $request)
    {
        abort_unless($request->user() !== null, 403);

        // The buyer's latest order, never one named by the query string: the
        // return URL is display-only and must not be a way to read another
        // buyer's order.
        $order = GiftCardOrder::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->first();

        return view('public.commerce.gift-card-return', [
            'order' => $order === null ? null : [
                'amount' => (string) $order->amount,
                'currency' => $order->currency,
                'recipient_name' => $order->recipient_name,
                'status' => $order->status,
                'delivered_to' => $order->delivered_to,
            ],
        ]);
    }
}
