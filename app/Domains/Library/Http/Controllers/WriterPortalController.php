<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\ListWriterDashboardAction;
use App\Domains\Library\Actions\ListWriterEarningsSummaryAction;
use App\Domains\Library\Actions\RequestWriterPayoutAction;
use App\Domains\Library\Actions\SaveWriterBankDetailsAction;
use App\Domains\Library\Actions\SaveWriterItemAction;
use App\Domains\Library\Actions\SubmitLibraryItemForReviewAction;
use App\Domains\Library\Enums\LibraryContentType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L5 writer portal (§7.4, §11) — new UI area, so Inertia. Thin: every rule
 * (own-items-only, editable-states, approval) lives in the actions.
 */
class WriterPortalController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Library/Write', [
            'dashboard' => app(ListWriterDashboardAction::class)->execute((int) $request->user()->id),
            'earnings' => app(ListWriterEarningsSummaryAction::class)->execute((int) $request->user()->id),
            'options' => [
                'content_types' => array_map(fn ($case) => $case->value, LibraryContentType::cases()),
            ],
        ]);
    }

    /** L6: where payouts go. */
    public function saveBankDetails(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
        ]);

        app(SaveWriterBankDetailsAction::class)->execute((int) $request->user()->id, $data);

        return back()->with('success', 'Bank details saved.');
    }

    /** L6: request the available balance (gated by library.payouts_enabled). */
    public function requestPayout(Request $request): RedirectResponse
    {
        app(RequestWriterPayoutAction::class)->execute((int) $request->user()->id);

        return back()->with('success', 'Payout requested — the admin will process it.');
    }

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:5000',
            'qualifications' => 'nullable|string|max:5000',
            'expertise' => 'nullable|string|max:255',
            'motivation' => 'nullable|string|max:5000',
            'agreement_accepted' => 'accepted',
        ]);

        app(ApplyAsWriterAction::class)->execute((int) $request->user()->id, $data);

        return back()->with('success', 'Application submitted — we will review it soon.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        app(SaveWriterItemAction::class)->execute(
            (int) $request->user()->id,
            $this->validatedItem($request),
            null,
            $request->file('pdf'),
        );

        return back()->with('success', 'Draft saved.');
    }

    public function updateItem(Request $request, int $item): RedirectResponse
    {
        app(SaveWriterItemAction::class)->execute(
            (int) $request->user()->id,
            $this->validatedItem($request),
            $item,
            $request->file('pdf'),
        );

        return back()->with('success', 'Draft updated.');
    }

    public function submit(Request $request, int $item): RedirectResponse
    {
        app(SubmitLibraryItemForReviewAction::class)->execute((int) $request->user()->id, $item);

        return back()->with('success', 'Submitted for review.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItem(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:10000',
            'abstract' => 'nullable|string|max:5000',
            'content_type' => 'required|string|max:30',
            'access_type' => 'nullable|string|max:20',
            'price' => 'nullable|numeric|min:0',
            'language' => 'nullable|string|max:5',
            'library_category_id' => 'nullable|integer',
            'body' => 'nullable|string',
            'citations' => 'nullable|string|max:20000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:60',
            'pdf' => 'nullable|file|mimes:pdf|max:51200',
        ]);
    }
}
