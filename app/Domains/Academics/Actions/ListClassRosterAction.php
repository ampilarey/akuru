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
            ->get(['id', 'first_name', 'last_name', 'student_id', 'status'])
            ->keyBy('id');

        return $roster->map(fn (ClassStudent $row) => [
            'id' => $row->id,
            'student_id' => $row->student_id,
            'name' => trim(($students[$row->student_id]->first_name ?? '').' '.($students[$row->student_id]->last_name ?? '')),
            'student_number' => $students[$row->student_id]->student_id ?? null,
            'status' => $row->status?->value,
        ]);
    }
}
