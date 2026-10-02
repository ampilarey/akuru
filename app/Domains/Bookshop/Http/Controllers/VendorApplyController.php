<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\ResolveVendorScopeAction;
use App\Domains\Bookshop\Actions\Shop\ApplyToSellAction;
use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BOOKSHOP_PLAN slice B9a: "Open a shop" (`/vendor/apply`). Any signed-in
 * person; the application is theirs alone. Thin: the rules — open or
 * closed, one waiting application, the agreement — are in the Action.
 */
class VendorApplyController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;
        $apply = app(ApplyToSellAction::class);

        return Inertia::render('Bookshop/VendorApply', [
            't' => Phrases::once('shop'),
            'open' => $apply->isOpen(),
            'application' => $apply->latest($userId),
            'shops' => app(ResolveVendorScopeAction::class)->memberships($userId),
            'agreement_url' => route('public.page.show', 'vendor-agreement'),
            'defaults' => ['contact_email' => $request->user()->email, 'contact_phone' => $request->user()->phone],
            'id_l' => trans('account'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // U1 (D1): a shop, or a person selling their own used books.
            'kind' => 'nullable|string|in:shop,personal',
            'shop_name' => 'required|string|max:120',
            'legal_name' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:40',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:40',
            'island' => 'required|string|max:120',
            'what_they_sell' => 'required|string|min:20|max:2000',
            'link' => 'nullable|url|max:255',
            'agreement' => 'accepted',
        ] + IdentityVerificationAction::fileRules());

        app(ApplyToSellAction::class)->execute((int) $request->user()->id, $data, $request->file('id_front'), $request->file('id_back'));

        return redirect()->route('vendor.apply')->with('success', __('shop.application_sent_flash'));
    }
}
