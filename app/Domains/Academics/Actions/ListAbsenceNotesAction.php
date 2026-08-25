<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\AbsenceNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListAbsenceNotesAction
{
    /**
     * @param  array{status?: string|null, student_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $notes = AbsenceNote::query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['student_ids'] ?? null, fn ($query, $ids) => $query->whereIn('student_id', $ids))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $students = DB::table('students')
            ->whereIn('id', $notes->pluck('student_id')->unique())
            ->get(['id', 'first_name', 'last_name', 'student_id'])
            ->keyBy('id');

        return $notes->map(function (AbsenceNote $note) use ($students) {
            $student = $students[$note->student_id] ?? null;

            return [
                'id' => $note->id,
                'student_id' => $note->student_id,
                'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'date' => $note->date?->toDateString(),
                'period_id' => $note->period_id,
                'reason' => $note->reason,
                'type' => $note->type,
                'status' => $note->status,
                'affects_attendance' => $note->affects_attendance,
                'review_notes' => $note->review_notes,
                'attachment_path' => $note->attachment_path,
            ];
        })->values();
    }
}
