<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Exam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListExamRosterAction
{
    /**
     * Students on the class roll as of the exam date (left-before excluded).
     *
     * @return Collection<int, array{student_id: int, name: string, student_number: mixed}>
     */
    public function execute(Exam $exam): Collection
    {
        $asOf = $exam->exam_date?->toDateString() ?? now()->toDateString();

        $rows = DB::table('class_student')
            ->where('class_id', $exam->class_id)
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('enrolled_at')->orWhereDate('enrolled_at', '<=', $asOf);
            })
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('left_at')->orWhereDate('left_at', '>=', $asOf);
            })
            ->get(['student_id']);

        $students = DB::table('students')
            ->whereIn('id', $rows->pluck('student_id'))
            ->get(['id', 'first_name', 'last_name', 'student_id'])
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($students) {
                $student = $students[$row->student_id] ?? null;
                if ($student === null) {
                    return null;
                }

                return [
                    'student_id' => (int) $student->id,
                    'name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                    'student_number' => $student->student_id,
                ];
            })
            ->filter()
            ->sortBy('name')
            ->values();
    }
}
