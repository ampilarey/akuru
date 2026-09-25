<?php

namespace App\Domains\Library\Http\Controllers;

use App\Domains\Library\Actions\ApplyAsWriterAction;
use App\Domains\Library\Actions\ListLibraryCategoriesAction;
use App\Domains\Library\Actions\ListWriterDashboardAction;
use App\Domains\Library\Actions\ListWriterEarningsSummaryAction;
use App\Domains\Library\Actions\ListWriterItemSalesAction;
use App\Domains\Library\Actions\RequestWriterPayoutAction;
use App\Domains\Library\Actions\SaveWriterBankDetailsAction;
use App\Domains\Library\Actions\SaveWriterItemAction;
use App\Domains\Library\Actions\SaveWriterPublicProfileAction;
use App\Domains\Library\Actions\SubmitLibraryItemForReviewAction;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Models\LibraryItem;
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
            'item_sales' => app(ListWriterItemSalesAction::class)->execute((int) $request->user()->id),
            'options' => [
                'content_types' => array_map(fn ($case) => $case->value, LibraryContentType::cases()),
                'categories' => app(ListLibraryCategoriesAction::class)->execute(),
                'languages' => ['en' => 'English', 'dv' => 'Dhivehi', 'ar' => 'Arabic'],
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

    /** L8: the author page's name, bio and portrait. */
    public function saveProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:5000',
            'qualifications' => 'nullable|string|max:5000',
            'expertise' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,webp|max:4096',
        ]);

        app(SaveWriterPublicProfileAction::class)->execute((int) $request->user()->id, $data, $request->file('photo'));

        return back()->with('success', 'Author page updated.');
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
        $saved = app(SaveWriterItemAction::class)->execute(
            (int) $request->user()->id,
            $this->validatedItem($request),
            null,
            $request->file('pdf'),
            $request->file('cover'),
        );

        return back()->with('success', 'Draft saved. '.$this->pagesNote($saved, $request->hasFile('pdf')));
    }

    public function updateItem(Request $request, int $item): RedirectResponse
    {
        $saved = app(SaveWriterItemAction::class)->execute(
            (int) $request->user()->id,
            $this->validatedItem($request),
            $item,
            $request->file('pdf'),
            $request->file('cover'),
        );

        return back()->with('success', 'Draft updated. '.$this->pagesNote($saved, $request->hasFile('pdf')));
    }

    /** What readers will get, said to the writer at save time. */
    private function pagesNote(LibraryItem $item, bool $pdfUploaded): string
    {
        $count = (int) $item->page_count;
        if ($count > 0) {
            return sprintf('%d reader page%s ready.', $count, $count === 1 ? '' : 's');
        }

        return $pdfUploaded || $item->pdf_media_file_id !== null
            ? 'Your PDF has no readable text (a scan or pictures), so readers would see no pages — paste the text into the body.'
            : 'No reader pages yet — add a body or upload a PDF.';
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
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:10000',
            'abstract' => 'nullable|string|max:5000',
            'content_type' => 'required|string|max:30',
            'access_type' => 'nullable|string|max:20',
            'price' => 'nullable|numeric|min:0',
            'language' => 'nullable|string|in:en,dv,ar',
            'library_category_id' => 'nullable|integer|exists:library_categories,id',
            'body' => 'nullable|string',
            'toc' => 'nullable|string|max:20000',
            'citations' => 'nullable|string|max:20000',
            'affiliation' => 'nullable|string|max:255',
            'research_field' => 'nullable|string|max:255',
            'suggested_reviewer' => 'nullable|string|max:255',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:60',
            'co_authors' => 'nullable|array|max:20',
            'co_authors.*' => 'nullable|string|max:120',
            'declarations' => 'nullable|array',
            'declarations.*' => 'nullable|boolean',
            'preview_enabled' => 'nullable|boolean',
            'preview_pages' => 'nullable|integer|min:1|max:1000',
            'pdf' => 'nullable|file|mimes:pdf|max:51200',
            'cover' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        return $data;
    }
}
