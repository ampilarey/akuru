<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;

class SyncEnrollmentProgressByIdAction
{
    public function execute(int $enrollmentId): void
    {
        $enrollment = CourseEnrollment::query()->find($enrollmentId);
        if ($enrollment === null) {
            return;
        }

        app(SyncEnrollmentProgressAction::class)->execute($enrollment);
    }
}
