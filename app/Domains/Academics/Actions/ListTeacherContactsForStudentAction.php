<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The teachers a student's family can reasonably write to: whoever teaches the
 * class on the timetable, plus the class teacher.
 *
 * E2a uses this as the messaging directory. A school directory that lists every
 * member of staff is how a parent ends up writing to the wrong person; the
 * people who teach the child are the honest default, and it needs no new
 * permission model to be safe.
 *
 * Only teachers with a login are returned — messaging someone who cannot read
 * their inbox is worse than not offering them.
 */
class ListTeacherContactsForStudentAction
{
    /**
     * @param  list<int>  $studentIds
     * @return Collection<int, array{user_id: int, teacher_id: int, name: string}>
     */
    public function execute(array $studentIds): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            return collect();
        }

        $classIds = DB::table('class_student')
            ->whereIn('student_id', $ids)
            ->where('status', ClassStudentStatus::Active->value)
            ->pluck('class_id')
            ->unique()
            ->values()
            ->all();

        if ($classIds === []) {
            return collect();
        }

        $teacherIds = DB::table('timetables')
            ->whereIn('class_id', $classIds)
            ->where('is_active', true)
            ->whereNotNull('teacher_id')
            ->pluck('teacher_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        // `classes.class_teacher_id` stores a users.id, not a teachers.id —
        // the two sources join on different columns and must not be merged.
        $classTeacherUserIds = DB::table('classes')
            ->whereIn('id', $classIds)
            ->whereNotNull('class_teacher_id')
            ->pluck('class_teacher_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($teacherIds === [] && $classTeacherUserIds === []) {
            return collect();
        }

        return DB::table('teachers')
            ->whereNotNull('user_id')
            ->where('status', 'active')
            ->where(function ($query) use ($teacherIds, $classTeacherUserIds): void {
                $query->whereIn('id', $teacherIds)
                    ->orWhereIn('user_id', $classTeacherUserIds);
            })
            ->orderBy('first_name')
            ->get(['id', 'user_id', 'first_name', 'last_name'])
            ->map(fn ($row): array => [
                'user_id' => (int) $row->user_id,
                'teacher_id' => (int) $row->id,
                'name' => trim($row->first_name.' '.$row->last_name),
            ])
            ->unique('user_id')
            ->values();
    }
}
