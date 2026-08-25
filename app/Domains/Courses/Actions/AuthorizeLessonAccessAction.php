<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\Lesson;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Domains\Progress\Actions\ListLessonProgressAction;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthorizeLessonAccessAction
{
    /**
     * @return array{lesson: Lesson, enrollment: CourseEnrollment|null, via: string}
     */
    public function execute(int $lessonId, ?Authenticatable $user): array
    {
        abort_unless($user !== null, 403);
        $lesson = Lesson::query()->with('module')->findOrFail($lessonId);

        if (method_exists($user, 'can') && $user->can('courses.manage')) {
            return ['lesson' => $lesson, 'enrollment' => null, 'via' => 'staff'];
        }

        abort_unless($lesson->current_revision_id !== null, 404, 'This lesson has no published revision.');

        $student = app(ResolveStudentForUserAction::class)->execute((int) $user->getAuthIdentifier());
        $enrollment = $student
            ? CourseEnrollment::query()
                ->where('course_id', $lesson->course_id)
                ->where('unified_student_id', $student['id'])
                ->whereIn('status', ['active', 'approved', 'completed'])
                ->first()
            : null;

        if ($lesson->is_preview) {
            return ['lesson' => $lesson, 'enrollment' => $enrollment, 'via' => 'preview'];
        }

        abort_unless($enrollment !== null, 403);
        abort_unless($this->isUnlocked($lesson, $enrollment->id), 403, 'This lesson is locked.');

        return ['lesson' => $lesson, 'enrollment' => $enrollment, 'via' => 'enrollment'];
    }

    public function isUnlocked(Lesson $lesson, int $enrollmentId): bool
    {
        if ($lesson->is_preview) {
            return $lesson->current_revision_id !== null;
        }

        $completed = collect(app(ListLessonProgressAction::class)->execute($enrollmentId))
            ->where('status', 'completed')
            ->pluck('lesson_id')
            ->all();

        foreach ($this->requiredLessons($lesson->course_id) as $item) {
            if ($item->id === $lesson->id) {
                return true;
            }
            if (! in_array($item->id, $completed, true)) {
                return false;
            }
        }

        return false;
    }

    /**
     * @return list<Lesson>
     */
    public function requiredLessons(int $courseId): array
    {
        return Lesson::query()
            ->where('course_id', $courseId)
            ->whereNotNull('current_revision_id')
            ->where('is_preview', false)
            ->with('module')
            ->get()
            ->sortBy(fn (Lesson $item) => sprintf('%05d-%05d', $item->module?->position ?? 0, $item->position))
            ->values()
            ->all();
    }
}
