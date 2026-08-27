<?php

namespace App\Domains\Commerce\Http\Controllers;

use App\Domains\Commerce\Actions\ListWalletAction;
use App\Domains\Commerce\Actions\RedeemGiftCardAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * L4 public wallet surface: balance + ledger + gift card redemption.
 */
class WalletController extends Controller
{
    public function show(Request $request)
    {
        abort_unless($request->user() !== null, 403);

        return view('public.commerce.wallet', [
            'wallet' => app(ListWalletAction::class)->execute((int) $request->user()->id),
        ]);
    }

    public function redeem(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate(['code' => 'required|string|max:40']);

        $result = app(RedeemGiftCardAction::class)->execute((int) $request->user()->id, $data['code']);

        return back()->with('success', 'Gift card redeemed: '.$result['credited'].' added to your wallet.');
    }
}
