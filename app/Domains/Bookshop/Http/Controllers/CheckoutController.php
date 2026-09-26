<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Checkout\PrepareCheckoutAction;
use App\Domains\Bookshop\Actions\Checkout\ServeBankTransferSlipAction;
use App\Domains\Bookshop\Actions\Checkout\StartBookshopCheckoutAction;
use App\Domains\Bookshop\Actions\Checkout\UploadBankTransferSlipAction;
use App\Domains\Bookshop\Actions\Orders\PresentCheckoutAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\ResolvesCart;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * BOOKSHOP_PLAN slice B2: checkout. Signed in only (`auth` on the routes
 * and re-checked here). The status page only DISPLAYS state — a card
 * payment is confirmed by the BML webhook listener, a bank transfer by the
 * office, never by anything that arrives here (rule 12).
 */
class CheckoutController extends Controller
{
    use ResolvesCart;

    public function show(Request $request)
    {
        abort_unless($request->user() !== null, 403);
        $checkout = app(PrepareCheckoutAction::class)->execute((int) $request->user()->id, $this->cart($request));
        if ($checkout['basket']['empty']) {
            return redirect()->route('public.shop.cart');
        }

        return view('public.shop.checkout', ['checkout' => $checkout, 'old' => $request->old()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'address_id' => 'nullable|integer',
            'recipient_name' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'atoll' => 'nullable|string|max:60',
            'island' => 'nullable|string|max:80',
            'street' => 'nullable|string|max:255',
            'address_notes' => 'nullable|string|max:500',
            'save_address' => 'nullable|boolean',
            'address_label' => 'nullable|string|max:40',
            'delivery' => 'required|array',
            'delivery.*' => 'required|string|max:20',
            // B9b: cash on delivery is checked shop by shop in the Action.
            'payment_method' => 'required|string|in:'.implode(',', [...(array) config('bookshop.checkout.methods'), 'cash_on_delivery']),
            'discount_code' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:1000',
            'gift_message' => 'nullable|string|max:300',
        ]);
        $cart = $this->cart($request);
        abort_if($cart === null, 404);

        $result = app(StartBookshopCheckoutAction::class)->execute(
            (int) $request->user()->id, $cart, $data,
            fn (string $number) => route('public.shop.checkout.status', $number),
        );

        return $this->afterStart($result);
    }

    /**
     * @param  array{checkout: \App\Domains\Bookshop\Models\BookshopCheckout, redirect_url: ?string, error: ?string, paid: bool}  $result
     */
    private function afterStart(array $result)
    {
        $status = route('public.shop.checkout.status', $result['checkout']->number);
        if ($result['redirect_url'] !== null) {
            return redirect()->away($result['redirect_url']);
        }
        if ($result['paid'] || $result['error'] === null) {
            return redirect()->to($status)->with('success', __('shop.order_placed_flash'));
        }

        return redirect()->to($status)->with('error', $result['error']);
    }

    public function status(Request $request, string $number)
    {
        abort_unless($request->user() !== null, 403);
        $checkout = app(PresentCheckoutAction::class)->execute((int) $request->user()->id, $number);
        abort_if($checkout === null, 404);

        return view('public.shop.checkout-status', ['checkout' => $checkout]);
    }

    public function uploadSlip(Request $request, string $number)
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'slip' => 'required|file|max:'.(int) config('bookshop.bank_transfer.slip_max_kilobytes', 8192),
            'reference' => 'nullable|string|max:80',
            'note' => 'nullable|string|max:500',
        ]);

        app(UploadBankTransferSlipAction::class)->execute((int) $request->user()->id, $number, $data['slip'], $data['reference'] ?? null, $data['note'] ?? null);

        return back()->with('success', __('shop.slip_sent_flash'));
    }

    public function slip(Request $request, int $slip)
    {
        abort_unless($request->user() !== null, 403);
        $read = app(ServeBankTransferSlipAction::class)->execute($slip, (int) $request->user()->id, (bool) $request->user()->can('bookshop.manage'));
        abort_if($read === null, 404);

        return response($read['contents'], 200, ['Content-Type' => $read['mime'], 'Content-Disposition' => 'inline; filename="'.addslashes($read['original_name']).'"']);
    }
}
