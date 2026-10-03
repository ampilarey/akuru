<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;

/**
 * Put a course on the website in one step (BACKLOG C16 slice N2). The
 * engine's workflow is Draft → In review → Published
 * (`CourseWorkflowStatus::allowedTransitions`) and the catalogue screen
 * walks it a step at a time; the office wants the course on the website, so
 * this walks both steps through `TransitionCourseWorkflowAction`, which
 * keeps the engine's own gate (`courses.publish`) on the last.
 *
 * @return array{outcome: 'published'|'already'|'archived', title: string}
 */
class PublishCourseAction
{
    public function execute(int $courseId, bool $canPublish): array
    {
        $course = Course::query()->findOrFail($courseId);
        $status = $course->workflow_status instanceof CourseWorkflowStatus
            ? $course->workflow_status
            : CourseWorkflowStatus::tryFrom((string) $course->workflow_status);

        if ($status === CourseWorkflowStatus::Archived) {
            return ['outcome' => 'archived', 'title' => $course->title];
        }
        if ($status === CourseWorkflowStatus::Published) {
            return ['outcome' => 'already', 'title' => $course->title];
        }

        $transition = app(TransitionCourseWorkflowAction::class);
        if ($status === CourseWorkflowStatus::Draft) {
            $course = $transition->execute($course, CourseWorkflowStatus::InReview, $canPublish);
        }
        $course = $transition->execute($course, CourseWorkflowStatus::Published, $canPublish);

        return ['outcome' => 'published', 'title' => $course->title];
    }
}
