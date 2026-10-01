<?php

namespace App\Domains\Lending\Actions;

use App\Domains\Lending\Enums\LendingBookStatus;
use App\Domains\Lending\Models\Lender;
use App\Domains\Lending\Models\LendingBook;
use Illuminate\Validation\ValidationException;

/**
 * The office's hand on lending (L2): pause a lender (their books leave the
 * shelf and they cannot resume themselves until the office does), resume
 * them, take a book down — each with a note the person reads.
 */
class ModerateLendingAction
{
    public function pauseLender(int $lenderId, ?string $note): Lender
    {
        $note = $this->note($note, required: true);
        $lender = Lender::query()->whereKey($lenderId)->firstOrFail();
        $lender->update(['status' => Lender::PAUSED, 'office_paused' => true, 'office_note' => $note]);
        app(NotifyLendingUserAction::class)->execute((int) $lender->user_id, __('lending.notice_office_paused_title'), __('lending.notice_office_paused_body', ['note' => $note]), '/my-lending#lender', 'office_paused');

        return $lender->refresh();
    }

    public function resumeLender(int $lenderId): Lender
    {
        $lender = Lender::query()->whereKey($lenderId)->firstOrFail();
        $lender->update(['status' => Lender::ACTIVE, 'office_paused' => false, 'office_note' => null]);
        app(NotifyLendingUserAction::class)->execute((int) $lender->user_id, __('lending.notice_office_resumed_title'), __('lending.notice_office_resumed_body'), '/my-lending#lender', 'office_resumed');

        return $lender->refresh();
    }

    public function removeBook(int $bookId, ?string $note): LendingBook
    {
        $note = $this->note($note, required: true);
        $book = LendingBook::query()->whereKey($bookId)->with('lender')->firstOrFail();
        if ($book->status === LendingBookStatus::OnLoan) {
            throw ValidationException::withMessages(['book' => __('lending.error_book_on_loan')]);
        }
        $book->update(['status' => LendingBookStatus::Removed->value, 'office_note' => $note]);
        app(NotifyLendingUserAction::class)->execute((int) $book->lender->user_id, __('lending.notice_office_removed_title'), __('lending.notice_office_removed_body', ['title' => $book->title, 'note' => $note]), '/my-lending#books', 'office_removed');

        return $book->refresh();
    }

    /**
     * L4: the office took the `lender` role away on Manage users, or gave it
     * back. A registered lender is paused (with a note they read) or resumed;
     * a person with no lender row is untouched — the role alone is an
     * invitation, and they register on My lending.
     */
    public function roleChanged(int $userId, bool $holdsRole): void
    {
        $lender = Lender::query()->where('user_id', $userId)->first();
        if ($lender === null) {
            return;
        }
        if (! $holdsRole && ! $lender->office_paused) {
            $this->pauseLender($lender->id, __('lending.office_role_removed_note'));
        } elseif ($holdsRole && $lender->office_paused) {
            $this->resumeLender($lender->id);
        }
    }

    private function note(?string $note, bool $required): ?string
    {
        $note = is_string($note) ? trim($note) : '';
        if ($note === '' && $required) {
            throw ValidationException::withMessages(['note' => __('lending.error_note_required')]);
        }

        return $note === '' ? null : mb_substr($note, 0, 500);
    }
}
