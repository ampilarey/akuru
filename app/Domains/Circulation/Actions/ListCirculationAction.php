<?php

namespace App\Domains\Circulation\Actions;

use App\Domains\Circulation\Enums\CopyStatus;
use App\Domains\Circulation\Models\BookCopy;
use App\Domains\Circulation\Models\BookTitle;
use App\Domains\Circulation\Models\Loan;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Availability lookup — the question a librarian is asked at the desk:
 * *"have you got it, and if not, when is it back?"*
 *
 * So each title carries its copy counts **and the earliest due date** among
 * the copies that are out. "None on the shelf" is a worse answer than "none
 * until Thursday".
 */
class ListCirculationAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(string $query = '', int $limit = 50): Collection
    {
        $query = trim($query);

        $titles = BookTitle::query()
            ->when($query !== '', function ($builder) use ($query): void {
                $like = '%'.$query.'%';
                $builder->where(function ($inner) use ($like): void {
                    $inner->where('title', 'like', $like)
                        ->orWhere('author', 'like', $like)
                        ->orWhere('isbn', 'like', $like)
                        ->orWhere('classification', 'like', $like);
                });
            })
            ->orderBy('title')
            ->limit($limit)
            ->get();

        if ($titles->isEmpty()) {
            return collect();
        }

        $counts = DB::table('book_copies')
            ->whereIn('book_title_id', $titles->modelKeys())
            ->selectRaw('book_title_id, status, count(*) as n')
            ->groupBy('book_title_id', 'status')
            ->get()
            ->groupBy('book_title_id');

        $soonestBack = DB::table('loans')
            ->join('book_copies', 'book_copies.id', '=', 'loans.book_copy_id')
            ->whereIn('book_copies.book_title_id', $titles->modelKeys())
            ->where('loans.status', 'out')
            ->selectRaw('book_copies.book_title_id, min(loans.due_on) as due')
            ->groupBy('book_copies.book_title_id')
            ->pluck('due', 'book_title_id');

        return $titles->map(function (BookTitle $title) use ($counts, $soonestBack): array {
            $forTitle = $counts->get($title->id, collect());
            $byStatus = fn (CopyStatus $status): int => (int) ($forTitle->firstWhere('status', $status->value)->n ?? 0);

            return [
                'id' => (int) $title->id,
                'title' => $title->title,
                'author' => $title->author,
                'isbn' => $title->isbn,
                'classification' => $title->classification,
                'loan_days' => (int) $title->loan_days,
                'available' => $byStatus(CopyStatus::Available),
                'on_loan' => $byStatus(CopyStatus::OnLoan),
                'total' => (int) $forTitle->sum('n'),
                // "None until Thursday" beats "none".
                'soonest_back' => $soonestBack->get($title->id),
            ];
        })->values();
    }

    /**
     * One title's copies, with who holds each — the librarian's second
     * question, once "have you got it" is answered with "not on the shelf".
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function copies(int $titleId): Collection
    {
        $copies = BookCopy::query()
            ->where('book_title_id', $titleId)
            ->orderBy('accession_number')
            ->get();

        if ($copies->isEmpty()) {
            return collect();
        }

        $loans = Loan::query()->out()->whereIn('book_copy_id', $copies->modelKeys())->get()->keyBy('book_copy_id');

        $students = app(ListStudentsByIdsAction::class)
            ->execute($loans->pluck('student_id')->filter()->all())
            ->keyBy('id');

        $staff = DB::table('users')
            ->whereIn('id', $loans->pluck('borrower_user_id')->filter()->all())
            ->pluck('name', 'id');

        return $copies->map(function (BookCopy $copy) use ($loans, $students, $staff): array {
            $loan = $loans->get($copy->id);

            $borrower = null;
            if ($loan !== null) {
                $borrower = $loan->student_id !== null
                    ? ($students->get((int) $loan->student_id)['name'] ?? 'Unknown pupil')
                    : ($staff->get((int) $loan->borrower_user_id) ?? 'Unknown staff');
            }

            return [
                'id' => (int) $copy->id,
                'accession_number' => $copy->accession_number,
                'status' => $copy->status->value,
                'status_label' => $copy->status->label(),
                'shelf' => $copy->shelf,
                'borrower' => $borrower,
                'due_on' => $loan?->due_on?->toDateString(),
                'overdue' => $loan?->isOverdue() ?? false,
            ];
        })->values();
    }
}
