<?php

namespace App\Domains\Lending\Http\Controllers;

use App\Domains\Lending\Actions\PresentLendingAdminAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** LENDING_AND_USED_BOOKS_PLAN L1: the office's view of lending — lenders, loans, the lenders' ID cards. Bookstore admins (D6). */
class AdminLendingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);

        return Inertia::render('Lending/Admin', [
            't' => trans('lending'),
            'id_l' => trans('account'),
            'admin' => app(PresentLendingAdminAction::class)->execute(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(PresentLendingAdminAction::class)->loans(5000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['book', 'lender', 'borrower', 'borrower_phone', 'status', 'requested_at', 'due_on', 'handed_at', 'returned_at']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['book'], $r['lender'], $r['borrower'], $r['borrower_phone'], $r['status'], $r['requested_at'], $r['due_on'], $r['handed_at'], $r['returned_at']]);
            }
            fclose($out);
        }, 'lending-loans.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
