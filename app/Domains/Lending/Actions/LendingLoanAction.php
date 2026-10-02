<?php

namespace App\Domains\Lending\Actions;

use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Domains\Lending\Enums\BookOffer;
use App\Domains\Lending\Enums\LendingBookStatus;
use App\Domains\Lending\Enums\LoanStatus;
use App\Domains\Lending\Models\Lender;
use App\Domains\Lending\Models\LendingBook;
use App\Domains\Lending\Models\LendingLoan;
use App\Domains\Lending\Support\LendingPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A loan from request to return (L1):
 *
 *  - a signed-in person **asks** for a book that is on the shelf and not
 *    their own, once at a time; the lender is told;
 *  - the lender **accepts** (naming the return date) or **declines** (with a
 *    note); the borrower is told; on acceptance both see each other's phone
 *    and arrange the handover themselves — no money through Akuru (D4);
 *  - the borrower may **cancel** until the book is handed over;
 *  - the lender marks **handed over** (the book is on loan) and **returned**
 *    (the book is available again).
 *
 * Every state change is one transaction with the book row locked, so two
 * requests for one book cannot both be accepted.
 */
class LendingLoanAction
{
    public function request(string $slug, int $borrowerUserId, ?string $message): LendingLoan
    {
        $loan = DB::transaction(function () use ($slug, $borrowerUserId, $message) {
            $book = LendingBook::query()->where('slug', $slug)->lockForUpdate()->with('lender')->firstOrFail();
            $lender = $book->lender;
            if ($book->status !== LendingBookStatus::Available || $lender->status !== Lender::ACTIVE || ! RegisterLenderAction::verified((int) $lender->user_id)) {
                throw ValidationException::withMessages(['book' => __('lending.error_not_available')]);
            }
            if ((int) $lender->user_id === $borrowerUserId) {
                throw ValidationException::withMessages(['book' => __('lending.error_own_book')]);
            }
            // L5: a give-away the giver has promised to someone is reserved until it is handed over or cancelled.
            if ($book->offer === BookOffer::Give && LendingLoan::query()->where('lending_book_id', $book->id)->where('status', LoanStatus::Accepted->value)->exists()) {
                throw ValidationException::withMessages(['book' => __('lending.error_reserved')]);
            }
            if ($lender->id_required && ! app(IdentityVerificationAction::class)->anyVerified([$borrowerUserId], 'vendor') && ! app(IdentityVerificationAction::class)->anyVerified([$borrowerUserId], 'lender')) {
                throw ValidationException::withMessages(['book' => __('lending.error_id_required')]);
            }
            if (LendingLoan::query()->where('lending_book_id', $book->id)->where('borrower_user_id', $borrowerUserId)->whereIn('status', [LoanStatus::Requested->value, LoanStatus::Accepted->value, LoanStatus::Out->value])->exists()) {
                throw ValidationException::withMessages(['book' => __('lending.error_already_asked')]);
            }
            $year = app(ResolveAcademicYearForDateAction::class)->execute();

            return LendingLoan::query()->create([
                'lending_book_id' => $book->id,
                'lender_id' => $lender->id,
                'borrower_user_id' => $borrowerUserId,
                'academic_year_id' => $year === null ? null : (int) $year['id'],
                'status' => LoanStatus::Requested->value,
                'message' => is_string($message) && trim($message) !== '' ? mb_substr(trim($message), 0, 500) : null,
                'requested_at' => now(),
            ]);
        });
        $borrower = $this->person($borrowerUserId);
        app(NotifyLendingUserAction::class)->execute((int) $loan->lender->user_id, __('lending.notice_requested_title'), __('lending.notice_requested_body', ['name' => $borrower['name'] ?? '', 'title' => $loan->book->title]), '/my-lending#lending', 'requested');

        return $loan;
    }

    public function accept(int $loanId, int $lenderUserId, ?string $dueOn): LendingLoan
    {
        $loan = DB::transaction(function () use ($loanId, $lenderUserId, $dueOn) {
            $loan = $this->ownLoan($loanId, $lenderUserId, [LoanStatus::Requested]);
            $book = LendingBook::query()->whereKey($loan->lending_book_id)->lockForUpdate()->firstOrFail();
            if ($book->status !== LendingBookStatus::Available) {
                throw ValidationException::withMessages(['loan' => __('lending.error_not_available')]);
            }
            // L3: a give-away has no return date.
            $due = $book->offer === BookOffer::Give ? null : ($dueOn !== null && $dueOn !== '' ? Carbon::parse($dueOn)->toDateString() : now()->addDays((int) $book->max_days)->toDateString());
            if ($due !== null && $due < now()->toDateString()) {
                throw ValidationException::withMessages(['due_on' => __('lending.error_due_past')]);
            }
            $loan->update(['status' => LoanStatus::Accepted->value, 'decided_at' => now(), 'due_on' => $due]);
            // Other open requests for the same book are declined: it is spoken for.
            LendingLoan::query()->where('lending_book_id', $book->id)->whereKeyNot($loan->id)->where('status', LoanStatus::Requested->value)
                ->each(fn (LendingLoan $other) => $this->declineQuietly($other, __('lending.note_lent_to_another')));

            return $loan->refresh();
        });
        $lender = $this->person($lenderUserId);
        $body = $loan->due_on === null
            ? __('lending.notice_accepted_give_body', ['title' => $loan->book->title, 'name' => $loan->lender->display_name, 'phone' => $lender['phone'] ?? '—'])
            : __('lending.notice_accepted_body', ['title' => $loan->book->title, 'name' => $loan->lender->display_name, 'phone' => $lender['phone'] ?? '—', 'due' => $loan->due_on->toDateString()]);
        app(NotifyLendingUserAction::class)->execute((int) $loan->borrower_user_id, __('lending.notice_accepted_title'), $body, '/my-lending#borrowing', 'accepted');

        return $loan;
    }

    public function decline(int $loanId, int $lenderUserId, ?string $note): LendingLoan
    {
        $note = is_string($note) ? trim($note) : '';
        if ($note === '') {
            throw ValidationException::withMessages(['note' => __('lending.error_note_required')]);
        }
        $loan = DB::transaction(function () use ($loanId, $lenderUserId, $note) {
            $loan = $this->ownLoan($loanId, $lenderUserId, [LoanStatus::Requested]);
            $loan->update(['status' => LoanStatus::Declined->value, 'decided_at' => now(), 'note' => mb_substr($note, 0, 500)]);

            return $loan->refresh();
        });
        app(NotifyLendingUserAction::class)->execute((int) $loan->borrower_user_id, __('lending.notice_declined_title'), __('lending.notice_declined_body', ['title' => $loan->book->title, 'note' => $note]), '/my-lending#borrowing', 'declined');

        return $loan;
    }

    public function cancel(int $loanId, int $borrowerUserId): LendingLoan
    {
        $loan = DB::transaction(function () use ($loanId, $borrowerUserId) {
            $loan = LendingLoan::query()->whereKey($loanId)->where('borrower_user_id', $borrowerUserId)->lockForUpdate()->firstOrFail();
            if (! in_array($loan->status, [LoanStatus::Requested, LoanStatus::Accepted], true)) {
                throw ValidationException::withMessages(['loan' => __('lending.error_cannot_cancel')]);
            }
            $loan->update(['status' => LoanStatus::Cancelled->value, 'decided_at' => $loan->decided_at ?? now()]);

            return $loan->refresh();
        });
        $borrower = $this->person($borrowerUserId);
        app(NotifyLendingUserAction::class)->execute((int) $loan->lender->user_id, __('lending.notice_cancelled_title'), __('lending.notice_cancelled_body', ['name' => $borrower['name'] ?? '', 'title' => $loan->book->title]), '/my-lending#lending', 'cancelled');

        return $loan;
    }

    public function handOver(int $loanId, int $lenderUserId): LendingLoan
    {
        $loan = DB::transaction(function () use ($loanId, $lenderUserId) {
            $loan = $this->ownLoan($loanId, $lenderUserId, [LoanStatus::Accepted]);
            if ($loan->book->offer === BookOffer::Give) {
                // L3: a give-away is done at handover — the book is theirs.
                $loan->update(['status' => LoanStatus::Given->value, 'handed_at' => now()]);
                LendingBook::query()->whereKey($loan->lending_book_id)->update(['status' => LendingBookStatus::Given->value]);
            } else {
                $loan->update(['status' => LoanStatus::Out->value, 'handed_at' => now()]);
                LendingBook::query()->whereKey($loan->lending_book_id)->update(['status' => LendingBookStatus::OnLoan->value]);
            }

            return $loan->refresh();
        });
        if ($loan->status === LoanStatus::Given) {
            app(NotifyLendingUserAction::class)->execute((int) $loan->borrower_user_id, __('lending.notice_given_title'), __('lending.notice_given_body', ['title' => $loan->book->title, 'name' => $loan->lender->display_name]), '/my-lending#borrowing', 'given');
        } else {
            app(NotifyLendingUserAction::class)->execute((int) $loan->borrower_user_id, __('lending.notice_out_title'), __('lending.notice_out_body', ['title' => $loan->book->title, 'due' => $loan->due_on?->toDateString() ?? '']), '/my-lending#borrowing', 'out');
        }

        return $loan;
    }

    public function markReturned(int $loanId, int $lenderUserId): LendingLoan
    {
        $loan = DB::transaction(function () use ($loanId, $lenderUserId) {
            $loan = $this->ownLoan($loanId, $lenderUserId, [LoanStatus::Out]);
            $loan->update(['status' => LoanStatus::Returned->value, 'returned_at' => now()]);
            LendingBook::query()->whereKey($loan->lending_book_id)->where('status', LendingBookStatus::OnLoan->value)->update(['status' => LendingBookStatus::Available->value]);

            return $loan->refresh();
        });
        app(NotifyLendingUserAction::class)->execute((int) $loan->borrower_user_id, __('lending.notice_returned_title'), __('lending.notice_returned_body', ['title' => $loan->book->title]), '/my-lending#borrowing', 'returned');

        return $loan;
    }

    /**
     * The loans on a lender's books, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function forLender(Lender $lender, int $limit = 100): array
    {
        $lenderPerson = $this->person((int) $lender->user_id);

        return LendingLoan::query()->where('lender_id', $lender->id)->with(['book', 'lender'])->orderByDesc('id')->limit($limit)->get()
            ->map(fn (LendingLoan $l) => LendingPresenter::loan($l, $this->person((int) $l->borrower_user_id, withId: true), $lenderPerson))->values()->all();
    }

    /**
     * What a person has asked to borrow, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function forBorrower(int $userId, int $limit = 100): array
    {
        $borrower = $this->person($userId);

        return LendingLoan::query()->where('borrower_user_id', $userId)->with(['book', 'lender'])->orderByDesc('id')->limit($limit)->get()
            ->map(fn (LendingLoan $l) => LendingPresenter::loan($l, $borrower, $this->person((int) $l->lender->user_id)))->values()->all();
    }

    /**
     * @param  list<LoanStatus>  $from
     */
    private function ownLoan(int $loanId, int $lenderUserId, array $from): LendingLoan
    {
        $lenderId = Lender::query()->where('user_id', $lenderUserId)->value('id');
        $loan = LendingLoan::query()->whereKey($loanId)->where('lender_id', (int) $lenderId)->lockForUpdate()->with(['book', 'lender'])->firstOrFail();
        if (! in_array($loan->status, $from, true)) {
            throw ValidationException::withMessages(['loan' => __('lending.error_wrong_state')]);
        }

        return $loan;
    }

    private function declineQuietly(LendingLoan $loan, string $note): void
    {
        $loan->update(['status' => LoanStatus::Declined->value, 'decided_at' => now(), 'note' => $note]);
        app(NotifyLendingUserAction::class)->execute((int) $loan->borrower_user_id, __('lending.notice_declined_title'), __('lending.notice_declined_body', ['title' => $loan->book->title, 'note' => $note]), '/my-lending#borrowing', 'declined');
    }

    /**
     * @return array{name?: string, phone?: ?string, id_verified?: bool}
     */
    private function person(int $userId, bool $withId = false): array
    {
        $userModel = config('auth.providers.users.model');
        $row = $userModel::query()->whereKey($userId)->first(['id', 'name', 'phone']);
        if ($row === null) {
            return [];
        }
        $out = ['name' => (string) $row->name, 'phone' => $row->phone];
        if ($withId) {
            $identity = app(IdentityVerificationAction::class);
            $out['id_verified'] = $identity->anyVerified([$userId], 'vendor') || $identity->anyVerified([$userId], 'lender');
        }

        return $out;
    }
}
