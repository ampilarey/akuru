<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The classes a member of staff may address: the ones on their timetable, plus
 * any they are class teacher of.
 *
 * The mirror of ListTeacherContactsForStudentAction (E2a), which answers the
 * same question from the family's side. Keeping both narrow means neither side
 * gets a directory of the whole school.
 *
 * `classes.class_teacher_id` stores a **users.id** while `timetables.teacher_id`
 * stores a **teachers.id** — two different id spaces that must not be merged.
 */
class ListClassesTaughtByUserAction
{
    /**
     * @return Collection<int, array{id: int, name: string, student_ids: list<int>}>
     */
    public function execute(int $userId): Collection
    {
        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($userId);

        $classIds = collect();

        if ($teacherId !== null) {
            $classIds = DB::table('timetables')
                ->where('teacher_id', $teacherId)
                ->where('is_active', true)
                ->pluck('class_id');
        }

        $classIds = $classIds
            ->merge(DB::table('classes')->where('class_teacher_id', $userId)->pluck('id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($classIds->isEmpty()) {
            return collect();
        }

        $rosters = DB::table('class_student')
            ->whereIn('class_id', $classIds->all())
            ->where('status', ClassStudentStatus::Active->value)
            ->get(['class_id', 'student_id'])
            ->groupBy('class_id');

        return DB::table('classes')
            ->whereIn('id', $classIds->all())
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('section')
            ->get(['id', 'name', 'section'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => trim($row->name.' '.($row->section ?? '')),
                'student_ids' => $rosters->get($row->id, collect())
                    ->pluck('student_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            ])
            ->values();
    }

    /** Whether this member of staff may address that class at all. */
    public function allows(int $userId, int $classId): bool
    {
        return $this->execute($userId)->contains(
            fn (array $row): bool => $row['id'] === $classId
        );
    }
}
