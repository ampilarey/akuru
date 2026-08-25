<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use Illuminate\Validation\ValidationException;

class TransitionCourseWorkflowAction
{
    public function execute(Course $course, CourseWorkflowStatus $to, bool $canPublish): Course
    {
        $from = $course->workflow_status instanceof CourseWorkflowStatus
            ? $course->workflow_status
            : CourseWorkflowStatus::tryFrom((string) $course->workflow_status);

        if ($from === null) {
            throw ValidationException::withMessages(['workflow_status' => 'Unknown current status.']);
        }

        if (! in_array($to, $from->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'workflow_status' => 'Cannot move from '.$from->value.' to '.$to->value.'.',
            ]);
        }

        if ($to === CourseWorkflowStatus::Published && ! $canPublish) {
            throw ValidationException::withMessages([
                'workflow_status' => 'Publishing requires courses.publish.',
            ]);
        }

        $course->workflow_status = $to;
        $course->save();

        return $course->refresh();
    }
}
