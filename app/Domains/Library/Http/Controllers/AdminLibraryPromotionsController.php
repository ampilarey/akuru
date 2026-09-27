<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Commerce\Actions\EndPromotionCampaignAction;
use App\Domains\Commerce\Actions\ListPromotionCampaignsAction;
use App\Domains\Commerce\Actions\SavePromotionCampaignAction;
use App\Domains\Library\Actions\ListPromotionTargetOptionsAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * B4 (LIBRARY_PLAN §18): the office's campaigns — start one, end one, see
 * how each did. Campaigns are Commerce's (rule 11); what they may cover is
 * the Library's, so the screen lives with the Library office and is gated
 * with it (`role:super_admin` + `library.manage`).
 */
class AdminLibraryPromotionsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Library/Promotions', [
            'campaigns' => app(ListPromotionCampaignsAction::class)->execute(),
            'options' => app(ListPromotionTargetOptionsAction::class)->execute(),
            'funding_sources' => SavePromotionCampaignAction::FUNDING_SOURCES,
            't' => trans('admin'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'funding_source' => 'required|in:'.implode(',', SavePromotionCampaignAction::FUNDING_SOURCES),
            'targets' => 'nullable|array|max:200',
            'targets.*.type' => 'required|in:'.implode(',', SavePromotionCampaignAction::TARGET_TYPES),
            'targets.*.id' => 'nullable|integer',
        ]);

        app(SavePromotionCampaignAction::class)->execute($data, (int) $request->user()->id);

        return back()->with('success', trans('admin.library_promotions_saved'));
    }

    public function end(int $campaign): RedirectResponse
    {
        app(EndPromotionCampaignAction::class)->execute($campaign);

        return back()->with('success', trans('admin.library_promotions_ended'));
    }

    /** Every listing gets a CSV. */
    public function export(): StreamedResponse
    {
        $campaigns = app(ListPromotionCampaignsAction::class)->execute();

        return response()->streamDownload(function () use ($campaigns): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['name', 'slug', 'starts_at', 'ends_at', 'discount_type', 'discount_value', 'max_discount_amount', 'funding_source', 'state', 'targets', 'uses', 'confirmed', 'given']);
            foreach ($campaigns as $row) {
                Csv::put($out, [
                    $row['name'], $row['slug'], $row['starts_at'], $row['ends_at'] ?? '', $row['discount_type'], $row['discount_value'], $row['max_discount_amount'] ?? '',
                    $row['funding_source'], $row['state'],
                    implode('; ', array_map(fn ($target) => $target['type'].($target['id'] !== null ? ':'.$target['id'] : ''), $row['targets'])),
                    $row['uses'], $row['confirmed'], $row['given'],
                ]);
            }
            fclose($out);
        }, 'library-promotions.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
