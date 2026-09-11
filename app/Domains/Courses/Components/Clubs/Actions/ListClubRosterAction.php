<?php

namespace App\Domains\Courses\Components\Clubs\Actions;

use App\Domains\Academics\Actions\ListStudentsOnActiveRosterAction;
use App\Domains\Courses\Actions\ListEnrollmentTargetsByCourseTypeAction;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;

/**
 * Who is in one club, and which of them is a pupil of the school.
 *
 * The plan's "support for members from outside the enrolled roll" needed no
 * schema: a `CourseEnrollment` only wants a student id, and nothing requires
 * that student to sit on a class roster. What it *did* need was for a club
 * leader taking a register to be able to tell the difference — a sibling, a
 * staff member's child or somebody who left last term is not a pupil, and the
 * register should say so rather than quietly implying they are.
 *
 * Cross-domain reads go through People's and Academics' own Actions (rule 3);
 * this component touches no model of any domain, its own included.
 */
class ListClubRosterAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $clubCourseId): Collection
    {
        $enrollments = collect(app(ListEnrollmentTargetsByCourseTypeAction::class)
            ->execute(ListClubsAction::COURSE_TYPE))
            ->filter(fn (array $row): bool => (int) $row['course_id'] === $clubCourseId)
            ->values();

        if ($enrollments->isEmpty()) {
            return collect();
        }

        $studentIds = $enrollments->pluck('student_id')->map(fn ($id): int => (int) $id)->all();

        $students = app(ListStudentsByIdsAction::class)->execute($studentIds)->keyBy('id');

        // One query for the whole roster rather than a lookup per member.
        $onRoll = array_flip(app(ListStudentsOnActiveRosterAction::class)->execute($studentIds));

        return $enrollments
            ->map(function (array $row) use ($students, $onRoll): array {
                $studentId = (int) $row['student_id'];
                $student = $students->get($studentId);

                return [
                    'enrollment_id' => (int) $row['enrollment_id'],
                    'student_id' => $studentId,
                    'name' => $student['name'] ?? 'Unknown',
                    'student_number' => $student['student_number'] ?? null,
                    // The whole point of the flag: a register that cannot tell
                    // a visitor from a pupil is a register nobody trusts.
                    'on_roll' => isset($onRoll[$studentId]),
                ];
            })
            ->sortBy('name')
            ->values();
    }
}
