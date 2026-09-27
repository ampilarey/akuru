<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;

/**
 * The enrolment numbers an administrator's home shows at a glance. Asked of
 * the owning domain (rule 3) rather than counted where the tile is drawn:
 * the super-admin dashboard counted these inline, and any second screen
 * that wanted the same figure would have counted them again its own way.
 */
class CountEnrollmentsAction
{
    /** Enrolments waiting on a decision or a payment. */
    public function pendingPayment(): int
    {
        return CourseEnrollment::query()->whereIn('status', ['pending', 'pending_payment'])->count();
    }

    /** Enrolments made today. */
    public function today(): int
    {
        return CourseEnrollment::query()->whereDate('created_at', today())->count();
    }

    /** Courses open to enrolment. */
    public function openCourses(): int
    {
        return Course::query()->where('status', 'open')->count();
    }
}
