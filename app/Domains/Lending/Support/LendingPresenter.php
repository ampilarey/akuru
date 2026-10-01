<?php

namespace App\Domains\Lending\Support;

use App\Domains\Lending\Actions\RateLendingAction;
use App\Domains\Lending\Models\Lender;
use App\Domains\Lending\Models\LendingBook;
use App\Domains\Lending\Models\LendingLoan;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;

/** The arrays the lending pages print (L1). The views only print; nothing here is looked up twice. */
final class LendingPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function book(LendingBook $book, ?Lender $lender = null, bool $large = false): array
    {
        $lender ??= $book->lender;
        $images = app(ResolvePublicImageVariantAction::class);

        return [
            'id' => $book->id,
            'slug' => $book->slug,
            'title' => $book->title,
            'author' => $book->author,
            'language' => $book->language,
            'offer' => $book->offer->value,
            'offer_label' => $book->offer->label(),
            'condition' => $book->condition->value,
            'condition_label' => $book->condition->label(),
            'description' => $book->description,
            'grade' => $book->grade,
            'subject' => $book->subject,
            'max_days' => (int) $book->max_days,
            'deposit' => $book->deposit,
            'status' => $book->status->value,
            'status_label' => $book->status->label(),
            'office_note' => $book->office_note,
            'photo' => $book->photo_media_id !== null ? $images->execute((int) $book->photo_media_id, (int) config('lending.photo.card_width', 480)) : null,
            'photo_large' => $large && $book->photo_media_id !== null ? $images->execute((int) $book->photo_media_id, (int) config('lending.photo.large_width', 1200)) : null,
            'url' => route('public.lending.show', $book->slug),
            'lender' => self::lender($lender),
        ];
    }

    /**
     * @return array{id: int, name: string, island: ?string, about: ?string, id_required: bool, rating: array{avg: ?float, count: int}}
     */
    public static function lender(Lender $lender): array
    {
        return [
            'rating' => RateLendingAction::lenderSummary($lender),
            'id' => $lender->id,
            'name' => $lender->display_name,
            'island' => $lender->island,
            'about' => $lender->about,
            'id_required' => (bool) $lender->id_required,
        ];
    }

    /**
     * One loan as either side sees it. Phones are shown only once the lender
     * has accepted: before that, neither side needs the other's number.
     *
     * @param  array{name?: string, phone?: ?string}  $borrower
     * @param  array{name?: string, phone?: ?string}  $lenderPerson
     * @return array<string, mixed>
     */
    public static function loan(LendingLoan $loan, array $borrower, array $lenderPerson): array
    {
        $accepted = in_array($loan->status->value, ['accepted', 'out', 'returned', 'given'], true);
        $ratings = $loan->status->isClosedWell() ? RateLendingAction::onLoan($loan) : [];

        return [
            'id' => $loan->id,
            'status' => $loan->status->value,
            'status_label' => $loan->status->label(),
            'open' => $loan->status->isOpen(),
            'message' => $loan->message,
            'note' => $loan->note,
            'requested_at' => $loan->requested_at?->toDateTimeString(),
            'decided_at' => $loan->decided_at?->toDateTimeString(),
            'handed_at' => $loan->handed_at?->toDateTimeString(),
            'due_on' => $loan->due_on?->toDateString(),
            'overdue' => $loan->status->value === 'out' && $loan->due_on !== null && $loan->due_on->isPast(),
            'returned_at' => $loan->returned_at?->toDateTimeString(),
            // L2: once returned, each side may rate the other once.
            'ratings' => $ratings,
            'book' => ['id' => $loan->book->id, 'slug' => $loan->book->slug, 'offer' => $loan->book->offer->value, 'title' => $loan->book->title, 'author' => $loan->book->author, 'url' => route('public.lending.show', $loan->book->slug), 'max_days' => (int) $loan->book->max_days, 'deposit' => $loan->book->deposit],
            'borrower' => ['name' => (string) ($borrower['name'] ?? ''), 'phone' => $accepted ? ($borrower['phone'] ?? null) : null, 'id_verified' => (bool) ($borrower['id_verified'] ?? false), 'rating' => RateLendingAction::borrowerSummary((int) $loan->borrower_user_id)],
            'lender' => ['name' => $loan->lender->display_name, 'island' => $loan->lender->island, 'phone' => $accepted ? ($lenderPerson['phone'] ?? null) : null],
        ];
    }
}
