<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ResolveLibrarySettingAction;
use App\Domains\Library\Actions\SaveLibrarySettingsAction;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * B12 (LIBRARY_PLAN §42): the Library's commercial knobs, on a screen. Gated
 * with the rest of the Library office (`role:super_admin` + `library.manage`).
 */
class AdminLibrarySettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Library/Settings', [
            'settings' => app(ResolveLibrarySettingAction::class)->all(),
            't' => Phrases::once('admin'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'refund_window_days' => 'required|integer',
            'default_writer_commission' => 'required|integer',
            'min_payout' => 'required|integer',
            'gift_card_min' => 'required|integer',
            'gift_card_max' => 'required|integer',
            'gift_card_expiry_months' => 'required|integer',
            'research_reviews_required' => 'required|integer|min:1|max:10',
            'payouts_enabled' => 'required|boolean',
            // STATUS §5lq: the important notices by email and SMS too.
            'notices_email' => 'sometimes|boolean',
            'notices_sms' => 'sometimes|boolean',
            // COMMERCE_PARITY_PLAN P5: the office's own address and number for a sale.
            'office_email' => 'nullable|email|max:255',
            'office_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,20}$/'],
        ]);

        app(SaveLibrarySettingsAction::class)->execute($data);

        return back()->with('success', trans('admin.library_settings_saved'));
    }
}
