<?php

namespace App\Domains\Lending\Actions;

use App\Domains\Lending\Enums\LoanStatus;
use App\Domains\Lending\Models\Lender;
use App\Domains\Lending\Models\LendingLoan;
use App\Domains\Lending\Models\LendingRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Ratings after a return (L2): the borrower rates the lender (shown on the
 * lender's books, for the next borrower) and the lender rates the borrower
 * (shown to the next lender they ask). One rating per person per loan,
 * only once the book is back; the other side is told.
 */
class RateLendingAction
{
    public function rate(int $loanId, int $byUserId, int $stars, ?string $comment): LendingRating
    {
        if ($stars < 1 || $stars > 5) {
            throw ValidationException::withMessages(['stars' => __('lending.error_stars')]);
        }
        $loan = LendingLoan::query()->whereKey($loanId)->with(['book', 'lender'])->firstOrFail();
        $isBorrower = (int) $loan->borrower_user_id === $byUserId;
        $isLender = (int) $loan->lender->user_id === $byUserId;
        abort_unless($isBorrower || $isLender, 404);
        if ($loan->status !== LoanStatus::Returned) {
            throw ValidationException::withMessages(['stars' => __('lending.error_rate_before_return')]);
        }
        if (LendingRating::query()->where('lending_loan_id', $loan->id)->where('by_user_id', $byUserId)->exists()) {
            throw ValidationException::withMessages(['stars' => __('lending.error_already_rated')]);
        }
        $comment = is_string($comment) && trim($comment) !== '' ? mb_substr(trim($comment), 0, 500) : null;
        $aboutUserId = $isBorrower ? (int) $loan->lender->user_id : (int) $loan->borrower_user_id;
        $rating = LendingRating::query()->create([
            'lending_loan_id' => $loan->id,
            'lender_id' => $loan->lender_id,
            'by_user_id' => $byUserId,
            'about_user_id' => $aboutUserId,
            'about' => $isBorrower ? LendingRating::ABOUT_LENDER : LendingRating::ABOUT_BORROWER,
            'stars' => $stars,
            'comment' => $comment,
        ]);
        app(NotifyLendingUserAction::class)->execute($aboutUserId, __('lending.notice_rated_title'), __('lending.notice_rated_body', ['title' => $loan->book->title, 'stars' => $stars]), $isBorrower ? '/my-lending#lending' : '/my-lending#borrowing', 'rated');

        return $rating;
    }

    /**
     * How borrowers rate this lender.
     *
     * @return array{avg: ?float, count: int}
     */
    public static function lenderSummary(Lender|int $lender): array
    {
        $id = $lender instanceof Lender ? $lender->id : $lender;

        return self::summary(LendingRating::query()->where('lender_id', $id)->where('about', LendingRating::ABOUT_LENDER));
    }

    /**
     * How lenders rate this person as a borrower.
     *
     * @return array{avg: ?float, count: int}
     */
    public static function borrowerSummary(int $userId): array
    {
        return self::summary(LendingRating::query()->where('about_user_id', $userId)->where('about', LendingRating::ABOUT_BORROWER));
    }

    /**
     * The ratings on a loan, keyed by who gave them.
     *
     * @return array<string, array{stars: int, comment: ?string}>
     */
    public static function onLoan(LendingLoan $loan): array
    {
        return LendingRating::query()->where('lending_loan_id', $loan->id)->get()
            ->mapWithKeys(fn (LendingRating $r) => [$r->about === LendingRating::ABOUT_LENDER ? 'by_borrower' : 'by_lender' => ['stars' => (int) $r->stars, 'comment' => $r->comment]])->all();
    }

    /**
     * Recent words from borrowers about a lender, for the book page.
     *
     * @return list<array{stars: int, comment: string, on: string}>
     */
    public static function lenderComments(int $lenderId, int $limit = 5): array
    {
        return LendingRating::query()->where('lender_id', $lenderId)->where('about', LendingRating::ABOUT_LENDER)->whereNotNull('comment')
            ->orderByDesc('id')->limit($limit)->get()
            ->map(fn (LendingRating $r) => ['stars' => (int) $r->stars, 'comment' => (string) $r->comment, 'on' => $r->created_at?->toDateString() ?? ''])->values()->all();
    }

    /**
     * @return array{avg: ?float, count: int}
     */
    private static function summary(Builder $query): array
    {
        $count = (int) (clone $query)->count();

        return ['avg' => $count === 0 ? null : round((float) (clone $query)->avg('stars'), 1), 'count' => $count];
    }
}
