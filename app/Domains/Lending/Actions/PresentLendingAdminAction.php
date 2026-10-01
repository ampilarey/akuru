<?php

namespace App\Domains\Lending\Actions;

use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Domains\Lending\Enums\LendingBookStatus;
use App\Domains\Lending\Enums\LoanStatus;
use App\Domains\Lending\Models\Lender;
use App\Domains\Lending\Models\LendingBook;
use App\Domains\Lending\Models\LendingLoan;
use App\Domains\Lending\Models\LendingRating;

/**
 * The office's view of lending (L1): every lender with their book and loan
 * counts and whether their ID card is checked, the recent loans, and the
 * lenders' ID cards to decide on. Run by the Bookstore team (D6).
 */
class PresentLendingAdminAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $limit = 200): array
    {
        $lenders = Lender::query()->withCount([
            'books as books_count' => fn ($q) => $q->where('status', '!=', LendingBookStatus::Removed->value),
            'loans as loans_count',
            'loans as open_loans_count' => fn ($q) => $q->whereIn('status', [LoanStatus::Requested->value, LoanStatus::Accepted->value, LoanStatus::Out->value]),
        ])->orderByDesc('id')->limit($limit)->get();
        $people = $this->people($lenders->pluck('user_id')->map(fn ($id) => (int) $id)->all());

        return [
            'counts' => [
                'lenders' => Lender::query()->count(),
                'books' => LendingBook::query()->where('status', '!=', LendingBookStatus::Removed->value)->count(),
                'open_loans' => LendingLoan::query()->whereIn('status', [LoanStatus::Requested->value, LoanStatus::Accepted->value, LoanStatus::Out->value])->count(),
                'overdue' => LendingLoan::query()->where('status', LoanStatus::Out->value)->whereDate('due_on', '<', now()->toDateString())->count(),
            ],
            'lenders' => $lenders->map(fn (Lender $l) => [
                'id' => $l->id,
                'display_name' => $l->display_name,
                'island' => $l->island,
                'status' => $l->status,
                'id_required' => (bool) $l->id_required,
                'office_paused' => (bool) $l->office_paused,
                'office_note' => $l->office_note,
                'rating' => RateLendingAction::lenderSummary($l),
                'books' => (int) $l->books_count,
                'loans' => (int) $l->loans_count,
                'open_loans' => (int) $l->open_loans_count,
                'person' => $people[(int) $l->user_id] ?? null,
                'id_verified' => RegisterLenderAction::verified((int) $l->user_id),
                'since' => $l->created_at?->toDateString(),
            ])->values()->all(),
            'loans' => $this->loans($limit),
            // L2: the books, for the office to take one down with a note.
            'books' => $this->books($limit),
            'identity' => app(IdentityVerificationAction::class)->list('lender'),
        ];
    }

    /**
     * The books on offer (not taken down), newest first (L2).
     *
     * @return list<array<string, mixed>>
     */
    public function books(int $limit = 200): array
    {
        return LendingBook::query()->where('status', '!=', LendingBookStatus::Removed->value)->with('lender')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (LendingBook $b) => ['id' => $b->id, 'slug' => $b->slug, 'title' => $b->title, 'author' => $b->author, 'lender' => $b->lender->display_name, 'lender_id' => $b->lender_id, 'status' => $b->status->value, 'status_label' => $b->status->label(), 'condition_label' => $b->condition->label(), 'since' => $b->created_at?->toDateString()])
            ->values()->all();
    }

    /**
     * Every lender as a CSV row (L2).
     *
     * @return list<array<string, mixed>>
     */
    public function lendersForExport(int $limit = 5000): array
    {
        $rows = $this->execute($limit)['lenders'];
        $ratings = LendingRating::query()->where('about', LendingRating::ABOUT_LENDER)->selectRaw('lender_id, avg(stars) as avg, count(*) as n')->groupBy('lender_id')->get()->keyBy('lender_id');

        return array_map(fn (array $l) => $l + ['rating_avg' => isset($ratings[$l['id']]) ? round((float) $ratings[$l['id']]->avg, 1) : null, 'rating_count' => (int) ($ratings[$l['id']]->n ?? 0)], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loans(int $limit = 200): array
    {
        $loans = LendingLoan::query()->with(['book', 'lender'])->orderByDesc('id')->limit($limit)->get();
        $people = $this->people($loans->pluck('borrower_user_id')->map(fn ($id) => (int) $id)->all());

        return $loans->map(fn (LendingLoan $l) => [
            'id' => $l->id,
            'book' => $l->book->title,
            'book_slug' => $l->book->slug,
            'lender' => $l->lender->display_name,
            'borrower' => $people[(int) $l->borrower_user_id]['name'] ?? '',
            'borrower_phone' => $people[(int) $l->borrower_user_id]['phone'] ?? null,
            'status' => $l->status->value,
            'status_label' => $l->status->label(),
            'requested_at' => $l->requested_at?->toDateTimeString(),
            'due_on' => $l->due_on?->toDateString(),
            'handed_at' => $l->handed_at?->toDateString(),
            'returned_at' => $l->returned_at?->toDateString(),
            'overdue' => $l->status === LoanStatus::Out && $l->due_on !== null && $l->due_on->isPast(),
        ])->values()->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{name: string, email: ?string, phone: ?string}>
     */
    private function people(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $userModel = config('auth.providers.users.model');

        return $userModel::query()->whereIn('id', array_unique($ids))->get(['id', 'name', 'email', 'phone'])
            ->mapWithKeys(fn ($u) => [(int) $u->id => ['name' => (string) $u->name, 'email' => $u->email, 'phone' => $u->phone]])->all();
    }
}
