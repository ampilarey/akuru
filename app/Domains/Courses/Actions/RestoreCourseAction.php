<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use Illuminate\Validation\ValidationException;

/**
 * The other half of `DeleteCourseAction` (SPEC §29).
 *
 * A restored course comes back **unpublished**. Deleting is usually a
 * withdrawal — a course created in error, a duplicate, something pulled
 * mid-intake — and a restore that silently re-listed it would publish content
 * to the public site as a side effect of an admin clicking "Restore". Whoever
 * restores it can publish it again deliberately.
 *
 * Its enrolments, attempts and payment line items were never touched, so they
 * are attached exactly as they were. That is the whole point of §29 keeping the
 * row rather than letting the foreign keys cascade.
 */
class RestoreCourseAction
{
    /**
     * @return array{title: string, slug: string, was_published: bool}
     */
    public function execute(Course $course): array
    {
        if (! $course->trashed()) {
            throw ValidationException::withMessages([
                'course' => 'That course has not been deleted.',
            ]);
        }

        // `workflow_status` is cast to an enum, so compare against the case
        // rather than a string.
        $wasPublished = $course->workflow_status === CourseWorkflowStatus::Published;

        $course->restore();

        if ($wasPublished) {
            $course->forceFill(['workflow_status' => CourseWorkflowStatus::Draft->value])->save();
        }

        return [
            'title' => (string) $course->title,
            'slug' => (string) $course->slug,
            'was_published' => $wasPublished,
        ];
    }
}
