<?php

namespace App\Domains\Lending\Actions;

use App\Domains\Lending\Enums\BookCondition;
use App\Domains\Lending\Enums\BookOffer;
use App\Domains\Lending\Enums\LendingBookStatus;
use App\Domains\Lending\Models\Lender;
use App\Domains\Lending\Models\LendingBook;
use App\Domains\Lending\Support\LendingPresenter;
use App\Domains\Media\Actions\StorePublicMediaAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A lender's own books (L1): add, change, take down. The slug is fixed at
 * creation — it is the book's address. A book on loan cannot be removed.
 */
class ManageLendingBooksAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(Lender $lender): array
    {
        return LendingBook::query()->where('lender_id', $lender->id)->where('status', '!=', LendingBookStatus::Removed->value)
            ->orderByDesc('id')->get()
            ->map(fn (LendingBook $b) => LendingPresenter::book($b, $lender))->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(Lender $lender, ?int $bookId, array $data, ?UploadedFile $photo = null): LendingBook
    {
        $text = fn (mixed $v, int $max) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;
        $title = $text($data['title'] ?? null, 255);
        if ($title === null) {
            throw ValidationException::withMessages(['title' => __('lending.error_title_required')]);
        }
        $book = $bookId === null ? null : LendingBook::query()->where('lender_id', $lender->id)->whereKey($bookId)->firstOrFail();
        if ($book === null && LendingBook::query()->where('lender_id', $lender->id)->where('status', '!=', LendingBookStatus::Removed->value)->count() >= (int) config('lending.max_books', 50)) {
            throw ValidationException::withMessages(['title' => __('lending.error_too_many_books', ['max' => (int) config('lending.max_books', 50)])]);
        }
        $days = (int) ($data['max_days'] ?? config('lending.default_days', 14));
        $days = max(1, min((int) config('lending.max_days', 60), $days));
        $condition = BookCondition::tryFrom((string) ($data['condition'] ?? '')) ?? BookCondition::Good;
        // L3: to lend or to give away; a book already on loan or given keeps its offer.
        $offer = BookOffer::tryFrom((string) ($data['offer'] ?? '')) ?? ($book?->offer ?? BookOffer::Lend);
        if ($book !== null && in_array($book->status, [LendingBookStatus::OnLoan, LendingBookStatus::Given], true)) {
            $offer = $book->offer;
        }

        $book ??= new LendingBook(['lender_id' => $lender->id, 'slug' => $this->uniqueSlug($title), 'status' => LendingBookStatus::Available->value]);
        $book->fill([
            'title' => $title,
            'author' => $text($data['author'] ?? null, 255),
            'language' => $text($data['language'] ?? null, 40),
            'condition' => $condition->value,
            'offer' => $offer->value,
            'description' => $text($data['description'] ?? null, 2000),
            'grade' => $text($data['grade'] ?? null, 40),
            'subject' => $text($data['subject'] ?? null, 80),
            'max_days' => $days,
            'deposit' => $text($data['deposit'] ?? null, 120),
        ]);
        if ($photo !== null) {
            $stored = app(StorePublicMediaAction::class)->execute($photo, (int) $lender->user_id, (array) config('lending.photo.mimes'), ['lender_id' => $lender->id], 'lending');
            $book->photo_media_id = (int) $stored['id'];
        }
        $book->save();

        return $book->refresh();
    }

    /** L2: pause a book (off the shelf, kept) or put it back; a book on loan or taken down stays as it is. */
    public function setStatus(Lender $lender, int $bookId, string $status): LendingBook
    {
        $book = LendingBook::query()->where('lender_id', $lender->id)->whereKey($bookId)->firstOrFail();
        if (! in_array($book->status, [LendingBookStatus::Available, LendingBookStatus::Paused], true)) {
            throw ValidationException::withMessages(['book' => __('lending.error_wrong_state')]);
        }
        $book->update(['status' => $status === 'pause' ? LendingBookStatus::Paused->value : LendingBookStatus::Available->value]);

        return $book->refresh();
    }

    public function remove(Lender $lender, int $bookId): void
    {
        $book = LendingBook::query()->where('lender_id', $lender->id)->whereKey($bookId)->firstOrFail();
        if ($book->status === LendingBookStatus::OnLoan) {
            throw ValidationException::withMessages(['book' => __('lending.error_book_on_loan')]);
        }
        $book->update(['status' => LendingBookStatus::Removed->value]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'book', 90, '');
        $slug = $base;
        for ($n = 2; LendingBook::query()->where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}
