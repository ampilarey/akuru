<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Offerings\Actions\ListScheduleSessionsAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;

class ListStudentScheduleAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId): array
    {
        $student = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($student === null) {
            return ['student' => null, 'sessions' => []];
        }

        $offeringIds = CourseEnrollment::query()
            ->where('unified_student_id', $student['id'])
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->pluck('course_offering_id')
            ->filter()
            ->all();

        return [
            'student' => $student,
            'sessions' => app(ListScheduleSessionsAction::class)->execute($offeringIds),
        ];
    }
}
