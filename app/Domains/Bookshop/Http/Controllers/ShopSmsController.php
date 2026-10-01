<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\ShopSmsCampaignAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * COMMERCE_PARITY_PLAN P7b: stopping the Bookstore's SMS offers — the link
 * at the end of every message (confirmed with a button, so a link preview
 * stops nobody), and the gateway's STOP keyword hook.
 */
class ShopSmsController extends Controller
{
    public function show(string $token)
    {
        $optin = app(ShopSmsCampaignAction::class)->find($token);
        abort_if($optin === null, 404);

        return view('public.shop.sms-stop', ['optin' => $optin, 'token' => $token, 'done' => (bool) session('sms_stopped')]);
    }

    public function stop(string $token): RedirectResponse
    {
        abort_if(app(ShopSmsCampaignAction::class)->find($token) === null, 404);
        app(ShopSmsCampaignAction::class)->optOut($token);

        return redirect()->route('public.shop.sms.stop', $token)->with('sms_stopped', true);
    }

    public function keyword(Request $request): Response
    {
        $data = $request->validate(['phone' => 'required|string|max:32', 'keyword' => 'required|string|max:64']);
        app(ShopSmsCampaignAction::class)->optOutByKeyword($data['phone'], $data['keyword']);

        return response()->noContent();
    }
}
