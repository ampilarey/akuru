<?php

namespace App\Domains\Lending\Http\Controllers;

use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Domains\Lending\Actions\LendingLoanAction;
use App\Domains\Lending\Actions\ManageLendingBooksAction;
use App\Domains\Lending\Actions\RateLendingAction;
use App\Domains\Lending\Actions\RegisterLenderAction;
use App\Domains\Lending\Enums\BookCondition;
use App\Domains\Lending\Enums\BookOffer;
use App\Domains\Lending\Models\Lender;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * LENDING_AND_USED_BOOKS_PLAN L1: My lending — becoming a lender, the
 * lender's ID card, the books they lend, the requests on them, and what
 * the person has asked to borrow. Every write is the signed-in person's
 * own: a lender is found by the user, never by an id from the request.
 */
class MyLendingController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) $request->user()->id;
        $status = app(RegisterLenderAction::class)->status($userId);
        $lender = $status['lender'] === null ? null : Lender::query()->whereKey($status['lender']['id'])->firstOrFail();

        return view('public.lending.mine', [
            'status' => $status,
            'books' => $lender === null ? [] : app(ManageLendingBooksAction::class)->list($lender),
            'lending' => $lender === null ? [] : app(LendingLoanAction::class)->forLender($lender),
            'borrowing' => app(LendingLoanAction::class)->forBorrower($userId),
            'conditions' => BookCondition::values(),
            'limits' => ['default_days' => (int) config('lending.default_days', 14), 'max_days' => (int) config('lending.max_days', 60), 'photo_kb' => (int) config('lending.photo.max_kilobytes', 5120)],
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => 'required|string|max:120',
            'island' => 'nullable|string|max:120',
            'about' => 'nullable|string|max:1000',
            'id_required' => 'nullable|boolean',
        ]);
        app(RegisterLenderAction::class)->execute((int) $request->user()->id, $data);

        return redirect()->to(route('public.lending.mine').'#lender')->with('success', __('lending.lender_saved_flash'));
    }

    /** L2: the lender pauses or resumes themselves; the route constrains the word. */
    public function status(Request $request, string $action): RedirectResponse
    {
        app(RegisterLenderAction::class)->setStatus((int) $request->user()->id, $action === 'pause' ? Lender::PAUSED : Lender::ACTIVE);

        return redirect()->to(route('public.lending.mine').'#lender')->with('success', __('lending.lender_'.$action.'_flash'));
    }

    /** L2: a book off the shelf for a while, or back on it. */
    public function bookStatus(Request $request, int $book, string $action): RedirectResponse
    {
        app(ManageLendingBooksAction::class)->setStatus($this->lender($request), $book, $action);

        return redirect()->to(route('public.lending.mine').'#books')->with('success', __('lending.book_'.$action.'_flash'));
    }

    /** L2: either side rates the other after a return. */
    public function rate(Request $request, int $loan): RedirectResponse
    {
        $data = $request->validate(['stars' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:500']);
        $rating = app(RateLendingAction::class)->rate($loan, (int) $request->user()->id, (int) $data['stars'], $data['comment'] ?? null);

        return redirect()->to(route('public.lending.mine').($rating->about === 'lender' ? '#borrowing' : '#lending'))->with('success', __('lending.rated_flash'));
    }

    public function identity(Request $request): RedirectResponse
    {
        $request->validate(IdentityVerificationAction::fileRules());
        app(RegisterLenderAction::class)->sendCard((int) $request->user()->id, $request->file('id_front'), $request->file('id_back'));

        return redirect()->to(route('public.lending.mine').'#lender')->with('success', __('account.id_sent_flash'));
    }

    public function storeBook(Request $request, ?int $book = null): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:40',
            'condition' => 'required|string|in:'.implode(',', BookCondition::values()),
            'offer' => 'nullable|string|in:'.implode(',', BookOffer::values()),
            'description' => 'nullable|string|max:2000',
            'grade' => 'nullable|string|max:40',
            'subject' => 'nullable|string|max:80',
            'max_days' => 'nullable|integer|min:1|max:'.(int) config('lending.max_days', 60),
            'deposit' => 'nullable|string|max:120',
            'photo' => 'nullable|file|image|max:'.(int) config('lending.photo.max_kilobytes', 5120),
        ]);
        app(ManageLendingBooksAction::class)->save($this->lender($request), $book, $data, $request->file('photo'));

        return redirect()->to(route('public.lending.mine').'#books')->with('success', __('lending.book_saved_flash'));
    }

    public function destroyBook(Request $request, int $book): RedirectResponse
    {
        app(ManageLendingBooksAction::class)->remove($this->lender($request), $book);

        return redirect()->to(route('public.lending.mine').'#books')->with('success', __('lending.book_removed_flash'));
    }

    /** One of accept, decline, cancel, handover, returned — the route constrains the word. */
    public function loan(Request $request, int $loan, string $action): RedirectResponse
    {
        $data = $request->validate(['due_on' => 'nullable|date', 'note' => 'nullable|string|max:500']);
        $userId = (int) $request->user()->id;
        $loans = app(LendingLoanAction::class);
        $result = match ($action) {
            'accept' => $loans->accept($loan, $userId, $data['due_on'] ?? null),
            'decline' => $loans->decline($loan, $userId, $data['note'] ?? null),
            'cancel' => $loans->cancel($loan, $userId),
            'handover' => $loans->handOver($loan, $userId),
            default => $loans->markReturned($loan, $userId),
        };
        $flash = $action === 'handover' && $result->status->value === 'given' ? 'loan_given_flash' : 'loan_'.$action.'_flash';

        return redirect()->to(route('public.lending.mine').($action === 'cancel' ? '#borrowing' : '#lending'))->with('success', __('lending.'.$flash));
    }

    private function lender(Request $request): Lender
    {
        $lender = Lender::query()->where('user_id', (int) $request->user()->id)->first();
        abort_if($lender === null, 403, __('lending.error_register_first'));

        return $lender;
    }
}
