<?php

namespace App\Domains\Circulation\Actions;

use App\Domains\Circulation\Models\BookCopy;
use App\Domains\Circulation\Models\Loan;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Loans, for the two screens that need them: the desk's overdue list and a
 * family's "what does my child have out".
 *
 * Overdue is derived from `due_on` rather than stored, so nothing has to run
 * overnight to keep a flag honest.
 */
class ListLoansAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function outstanding(bool $overdueOnly = false, int $limit = 200): Collection
    {
        $loans = Loan::query()
            ->out()
            ->when($overdueOnly, fn ($query) => $query->whereDate('due_on', '<', now()->toDateString()))
            ->orderBy('due_on')
            ->limit($limit)
            ->get();

        return $this->decorate($loans);
    }

    /**
     * @param  list<int>  $studentIds  already scoped to this guardian by the caller
     * @return Collection<int, array<string, mixed>>
     */
    public function forStudents(array $studentIds, bool $includeReturned = false): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));

        if ($ids === []) {
            return collect();
        }

        $loans = Loan::query()
            ->whereIn('student_id', $ids)
            ->when(! $includeReturned, fn ($query) => $query->out())
            ->orderBy('due_on')
            ->get();

        return $this->decorate($loans);
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @return Collection<int, array<string, mixed>>
     */
    private function decorate(Collection $loans): Collection
    {
        if ($loans->isEmpty()) {
            return collect();
        }

        $copies = BookCopy::query()
            ->with('title')
            ->whereIn('id', $loans->pluck('book_copy_id')->all())
            ->get()
            ->keyBy('id');

        $students = app(ListStudentsByIdsAction::class)
            ->execute($loans->pluck('student_id')->filter()->all())
            ->keyBy('id');

        $staff = DB::table('users')
            ->whereIn('id', $loans->pluck('borrower_user_id')->filter()->all())
            ->pluck('name', 'id');

        return $loans->map(function (Loan $loan) use ($copies, $students, $staff): array {
            $copy = $copies->get((int) $loan->book_copy_id);

            return [
                'id' => (int) $loan->id,
                'accession_number' => $copy?->accession_number,
                'title' => $copy?->title?->title ?? 'Unknown title',
                'borrower' => $loan->student_id !== null
                    ? ($students->get((int) $loan->student_id)['name'] ?? 'Unknown pupil')
                    : ($staff->get((int) $loan->borrower_user_id) ?? 'Unknown staff'),
                'is_staff' => $loan->student_id === null,
                'out_on' => $loan->out_on?->toDateString(),
                'due_on' => $loan->due_on?->toDateString(),
                'returned_on' => $loan->returned_on?->toDateString(),
                'status' => $loan->status->value,
                'status_label' => $loan->status->label(),
                'overdue' => $loan->isOverdue(),
                // Carbon's diff is signed, and a due date in the past yields a
                // negative. Whole days, always positive.
                'days_overdue' => $loan->isOverdue()
                    ? (int) abs(now()->startOfDay()->diffInDays($loan->due_on))
                    : 0,
            ];
        })->values();
    }
}
