<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\ListingApprovalAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * COMMERCE_PARITY_PLAN P4: the office's queue of listings awaiting approval,
 * shown on `/admin/bookshop` — approve (on sale) or decline with a note.
 */
class AdminListingController extends Controller
{
    public function decide(Request $request, int $product): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate([
            'decision' => 'required|string|in:approve,decline',
            'note' => 'nullable|string|max:1000',
        ]);

        $decided = app(ListingApprovalAction::class)->execute($product, (int) $request->user()->id, $data['decision'] === 'approve', $data['note'] ?? null);

        return back()->with('success', __($data['decision'] === 'approve' ? 'shop.listing_approved_flash' : 'shop.listing_declined_flash', ['title' => $decided->title]));
    }

    /** Every listing gets a CSV (conventions): waiting, and the recent decisions. */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(ListingApprovalAction::class)->queue(5000, decided: true);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['product', 'shop', 'category', 'price', 'status', 'submitted', 'changed', 'decided', 'note']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['title'], $r['vendor'], $r['category'], $r['price'], $r['status'], $r['submitted_at'], implode(' ', array_keys((array) $r['changes'])), $r['reviewed_at'], $r['review_note']]);
            }
            fclose($out);
        }, 'bookstore-listings.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
