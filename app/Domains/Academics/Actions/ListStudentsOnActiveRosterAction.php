<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassStudent;

/**
 * Which of these students are on an active class roster in the current year.
 *
 * `StudentIsOnClassRosterAction` answers "is this student in *that* class",
 * which is a different question and cannot be asked in bulk without N+1.
 *
 * Exists for E17: a club may include members from outside the enrolled roll —
 * a sibling, a staff member's child, somebody who left last term — and a club
 * leader taking a register needs to know which of the names in front of them
 * is a pupil of the school and which is a visitor.
 *
 * Returns ids rather than booleans so the caller can ask about a whole roster
 * in one query.
 */
class ListStudentsOnActiveRosterAction
{
    /**
     * @param  list<int>  $studentIds
     * @return list<int>
     */
    public function execute(array $studentIds, ?int $yearId = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));

        if ($ids === []) {
            return [];
        }

        $yearId ??= (int) AcademicYear::query()->where('status', 'active')->value('id');

        if ($yearId === 0) {
            return [];
        }

        // `class_student` carries `academic_year_id` itself (rule 10), so this
        // needs no join through the classroom.
        return ClassStudent::query()
            ->whereIn('student_id', $ids)
            ->where('academic_year_id', $yearId)
            ->where('status', ClassStudentStatus::Active->value)
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
