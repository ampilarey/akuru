<?php

namespace App\Domains\Lending\Http\Controllers;

use App\Domains\Lending\Actions\LendingLoanAction;
use App\Domains\Lending\Actions\ListLendingBooksAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * LENDING_AND_USED_BOOKS_PLAN L1: the public shelf of books to borrow, a
 * book's page, and a signed-in person's request to borrow one. Thin: who
 * may see what and what a request may do are the Actions'.
 */
class LendingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'offer' => 'nullable|string|in:lend,give',
            'grade' => 'nullable|string|max:40',
            'subject' => 'nullable|string|max:80',
            'language' => 'nullable|string|max:40',
            'island' => 'nullable|string|max:120',
        ]);
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        return view('public.lending.index', ['shelf' => app(ListLendingBooksAction::class)->execute($filters), 'filters' => $filters]);
    }

    /** L5: the Free items page — what people give away, reserved and recently taken. */
    public function free()
    {
        return view('public.lending.free', ['free' => app(ListLendingBooksAction::class)->free()]);
    }

    public function show(Request $request, string $slug)
    {
        $book = app(ListLendingBooksAction::class)->show($slug);
        abort_if($book === null, 404);

        return view('public.lending.show', ['book' => $book, 'viewer' => $request->user()?->id]);
    }

    public function request(Request $request, string $slug): RedirectResponse
    {
        $data = $request->validate(['message' => 'nullable|string|max:500']);

        app(LendingLoanAction::class)->request($slug, (int) $request->user()->id, $data['message'] ?? null);

        return redirect()->to(route('public.lending.mine').'#borrowing')->with('success', __('lending.requested_flash'));
    }
}
