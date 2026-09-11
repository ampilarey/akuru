<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\StudentWork;
use App\Domains\Academics\Models\StudentWorkReassignment;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The staff view: everything photographed, hidden rows included.
 *
 * Each row carries how many times it has been moved. A photo that has bounced
 * between three pupils is worth a second look before anybody trusts it, and
 * the count is the cheapest way to surface that.
 */
class ListStudentWorkAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $studentId = null, int $limit = 100): Collection
    {
        $work = StudentWork::query()
            ->when($studentId !== null, fn ($query) => $query->where('student_id', $studentId))
            ->orderByDesc('done_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($work->isEmpty()) {
            return collect();
        }

        $students = app(ListStudentsByIdsAction::class)
            ->execute($work->pluck('student_id')->all())
            ->keyBy('id');

        $staff = DB::table('users')
            ->whereIn('id', $work->pluck('uploaded_by')->filter()->all())
            ->pluck('name', 'id');

        $moves = StudentWorkReassignment::query()
            ->whereIn('student_work_id', $work->modelKeys())
            ->get()
            ->groupBy('student_work_id');

        return $work->map(fn (StudentWork $row): array => [
            'id' => (int) $row->id,
            'student_id' => (int) $row->student_id,
            'student' => $students->get((int) $row->student_id)['name'] ?? 'Unknown',
            'student_number' => $students->get((int) $row->student_id)['student_number'] ?? null,
            'title' => $row->title,
            'note' => $row->note,
            'done_on' => $row->done_on?->toDateString(),
            'uploaded_by' => $staff->get((int) $row->uploaded_by) ?? 'Unknown',
            'hidden' => $row->hidden_at !== null,
            'times_moved' => ($moves->get((int) $row->id)?->count()) ?? 0,
        ])->values();
    }
}
