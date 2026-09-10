<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use Illuminate\Support\Facades\DB;

/**
 * Which of these pupils are actively on any of these classes.
 *
 * Exists so other domains can ask the roster question without reaching into
 * Academics' own table or its `ClassStudentStatus` enum — **an enum is not one
 * of the layers rule 2 allows across a boundary**, and E6c's first attempt at
 * this imported one.
 *
 * @see ResolveAudienceContextAction for the richer "is this aimed at me?" case
 */
class ListStudentIdsOnClassesAction
{
    /**
     * @param  list<int>  $studentIds
     * @param  list<int>  $classIds
     * @return list<int>
     */
    public function execute(array $studentIds, array $classIds): array
    {
        $students = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        $classes = array_values(array_unique(array_filter(array_map('intval', $classIds))));

        if ($students === [] || $classes === []) {
            return [];
        }

        return DB::table('class_student')
            ->whereIn('student_id', $students)
            ->whereIn('class_id', $classes)
            ->where('status', ClassStudentStatus::Active->value)
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
