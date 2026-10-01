<?php

namespace App\Domains\Lending\Actions;

use App\Domains\Lending\Enums\LendingBookStatus;
use App\Domains\Lending\Models\Lender;
use App\Domains\Lending\Models\LendingBook;
use App\Domains\Lending\Support\LendingPresenter;
use Illuminate\Database\Eloquent\Builder;

/**
 * The public shelf (L1): books available now from active lenders whose ID
 * card the office has checked (D5), searchable by words and narrowed by
 * grade, subject, language or island. A book on loan is listed too, marked
 * so — a borrower may want to ask for it next — but a paused or removed one
 * is not.
 */
class ListLendingBooksAction
{
    /**
     * @param  array<string, mixed>  $filters  q, grade, subject, language, island
     * @return array{books: list<array<string, mixed>>, total: int, filters: array<string, list<string>>}
     */
    public function execute(array $filters = []): array
    {
        $query = $this->shelf()
            ->when(($filters['q'] ?? '') !== '', function (Builder $q) use ($filters) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $filters['q'])).'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $term)->orWhere('author', 'like', $term)->orWhere('subject', 'like', $term));
            })
            ->when(($filters['grade'] ?? '') !== '', fn (Builder $q) => $q->where('grade', (string) $filters['grade']))
            ->when(($filters['subject'] ?? '') !== '', fn (Builder $q) => $q->where('subject', (string) $filters['subject']))
            ->when(($filters['language'] ?? '') !== '', fn (Builder $q) => $q->where('language', (string) $filters['language']))
            ->when(($filters['island'] ?? '') !== '', fn (Builder $q) => $q->whereHas('lender', fn (Builder $l) => $l->where('island', (string) $filters['island'])));

        $books = $query->with('lender')->orderByRaw('case when status = ? then 0 else 1 end', [LendingBookStatus::Available->value])->orderByDesc('id')->limit((int) config('lending.per_page', 24))->get();

        return [
            'books' => $books->map(fn (LendingBook $b) => LendingPresenter::book($b))->values()->all(),
            'total' => (clone $query)->count(),
            'filters' => $this->choices(),
        ];
    }

    /**
     * One book's page, or null when it is not on the shelf.
     *
     * @return array<string, mixed>|null
     */
    public function show(string $slug): ?array
    {
        $book = $this->shelf()->where('slug', $slug)->with('lender')->first();

        if ($book === null) {
            return null;
        }

        // L2: what borrowers said about this lender.
        return LendingPresenter::book($book, null, true) + ['lender_comments' => RateLendingAction::lenderComments((int) $book->lender_id)];
    }

    /** Books that may be shown: not paused or removed, from an active, ID-checked lender. */
    private function shelf(): Builder
    {
        $lenderIds = Lender::query()->where('status', Lender::ACTIVE)->get(['id', 'user_id'])
            ->filter(fn (Lender $l) => RegisterLenderAction::verified((int) $l->user_id))->pluck('id')->all();

        return LendingBook::query()
            ->whereIn('status', [LendingBookStatus::Available->value, LendingBookStatus::OnLoan->value])
            ->whereIn('lender_id', $lenderIds);
    }

    /**
     * What the filters may be set to: the values the shelf holds.
     *
     * @return array<string, list<string>>
     */
    private function choices(): array
    {
        $shelf = $this->shelf();
        $pluck = fn (string $column) => (clone $shelf)->whereNotNull($column)->where($column, '!=', '')->distinct()->orderBy($column)->pluck($column)->map(fn ($v) => (string) $v)->values()->all();

        return [
            'grade' => $pluck('grade'),
            'subject' => $pluck('subject'),
            'language' => $pluck('language'),
            'island' => Lender::query()->whereIn('id', (clone $shelf)->distinct()->pluck('lender_id'))->whereNotNull('island')->distinct()->orderBy('island')->pluck('island')->map(fn ($v) => (string) $v)->values()->all(),
        ];
    }
}
