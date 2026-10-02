<?php

namespace App\Domains\Lending\Http\Controllers;

use App\Domains\Lending\Actions\ModerateLendingAction;
use App\Domains\Lending\Actions\PresentLendingAdminAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * LENDING_AND_USED_BOOKS_PLAN L1/L2: the office's view of lending — lenders,
 * books, loans, the lenders' ID cards — and its hand: pause or resume a
 * lender, take a book down, each with a note. Bookstore admins (D6).
 */
class AdminLendingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);

        return Inertia::render('Lending/Admin', [
            't' => Phrases::once('lending'),
            'id_l' => trans('account'),
            'admin' => app(PresentLendingAdminAction::class)->execute(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(PresentLendingAdminAction::class)->loans(5000);
        $columns = ['book', 'lender', 'borrower', 'borrower_phone', 'status', 'requested_at', 'due_on', 'handed_at', 'returned_at'];

        return $this->csv('lending-loans.csv', $columns, $rows);
    }

    /** L2: every lender, with their counts, ID state and rating. */
    public function exportLenders(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = array_map(fn (array $l) => $l + ['name' => $l['person']['name'] ?? '', 'phone' => $l['person']['phone'] ?? '', 'email' => $l['person']['email'] ?? ''], app(PresentLendingAdminAction::class)->lendersForExport());
        $columns = ['display_name', 'name', 'phone', 'email', 'island', 'status', 'office_paused', 'id_verified', 'books', 'loans', 'open_loans', 'rating_avg', 'rating_count', 'since'];

        return $this->csv('lenders.csv', $columns, $rows);
    }

    /** L2: pause (with a note) or resume a lender; the route constrains the word. */
    public function lender(Request $request, int $lender, string $action): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['note' => 'nullable|string|max:500']);
        $moderate = app(ModerateLendingAction::class);
        $action === 'pause' ? $moderate->pauseLender($lender, $data['note'] ?? null) : $moderate->resumeLender($lender);

        return back()->with('success', __('lending.office_lender_'.$action.'_flash'));
    }

    /** L2: take a book down, with a note the lender reads. */
    public function removeBook(Request $request, int $book): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['note' => 'required|string|max:500']);
        app(ModerateLendingAction::class)->removeBook($book, $data['note']);

        return back()->with('success', __('lending.office_book_removed_flash'));
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function csv(string $name, array $columns, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, $columns);
            foreach ($rows as $r) {
                Csv::put($out, array_map(fn (string $c) => is_bool($r[$c] ?? null) ? ($r[$c] ? 'yes' : 'no') : ($r[$c] ?? ''), $columns));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
