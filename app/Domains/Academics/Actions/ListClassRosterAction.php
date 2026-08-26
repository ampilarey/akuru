<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Models\ClassStudent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListClassRosterAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $classId): Collection
    {
        $roster = ClassStudent::query()
            ->where('class_id', $classId)
            ->where('status', ClassStudentStatus::Active->value)
            ->get();

        $students = DB::table('students')
            ->whereIn('id', $roster->pluck('student_id'))
            ->get(['id', 'first_name', 'last_name', 'student_id', 'date_of_birth', 'status'])
            ->keyBy('id');

        return $roster->map(fn (ClassStudent $row) => [
            'id' => $row->id,
            'student_id' => $row->student_id,
            'name' => trim(($students[$row->student_id]->first_name ?? '').' '.($students[$row->student_id]->last_name ?? '')),
            'student_number' => $students[$row->student_id]->student_id ?? null,
            'date_of_birth' => $students[$row->student_id]->date_of_birth
                ? substr((string) $students[$row->student_id]->date_of_birth, 0, 10)
                : null,
            'status' => $row->status?->value,
        ]);
    }
}
