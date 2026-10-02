<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\ShopSmsCampaignAction;
use App\Domains\Bookshop\Models\ShopSmsCampaign;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * COMMERCE_PARITY_PLAN P7b: the office's SMS offers — who would get one,
 * what it costs before it goes, the month's budget, and what was sent.
 */
class AdminSmsCampaignController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $campaigns = app(ShopSmsCampaignAction::class);

        return Inertia::render('Bookshop/Campaigns', ['t' => Phrases::once('shop'), 'summary' => $campaigns->summary(), 'campaigns' => $campaigns->list()]);
    }

    public function send(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'audience' => 'required|string|in:'.implode(',', ShopSmsCampaign::AUDIENCES),
            'vendor_id' => 'nullable|required_if:audience,shop_buyers|integer|exists:vendors,id',
            'message' => 'required|string|max:600',
        ]);
        $campaign = app(ShopSmsCampaignAction::class)->send((int) $request->user()->id, $data['audience'], isset($data['vendor_id']) ? (int) $data['vendor_id'] : null, $data['message']);

        return back()->with('success', __('shop.campaign_queued_flash', ['count' => $campaign->recipients, 'cost' => (string) $campaign->cost]));
    }

    public function settings(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['rate' => 'required|numeric|min:0|max:100', 'budget' => 'required|numeric|min:0|max:1000000']);
        app(ShopSmsCampaignAction::class)->saveSettings((float) $data['rate'], (float) $data['budget']);

        return back()->with('success', __('shop.campaign_settings_saved'));
    }

    /** Every listing gets a CSV (conventions). */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(ShopSmsCampaignAction::class)->list(5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['sent', 'audience', 'shop', 'message', 'recipients', 'messages_each', 'cost', 'status', 'delivered', 'failed']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['created_at'], $r['audience'], $r['shop'], $r['message'], $r['recipients'], $r['segments'], $r['cost'], $r['status'], $r['sent'], $r['failed']]);
            }
            fclose($out);
        }, 'bookstore-sms-campaigns.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
