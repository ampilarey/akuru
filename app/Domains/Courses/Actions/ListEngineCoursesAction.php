<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use Illuminate\Support\Collection;

class ListEngineCoursesAction
{
    /**
     * @param  string|null  $courseType  Filter to one course_type. The **value**
     *                                   comes from the caller — 'club' from the
     *                                   Clubs component, 'hifz' from Quran — so
     *                                   the engine stays subject-ignorant
     *                                   (rule 6), exactly as
     *                                   ListEnrollmentTargetsByCourseTypeAction
     *                                   already does for enrollments.
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?string $courseType = null): Collection
    {
        $subjects = app(ListCourseSubjectsAction::class)->execute()->keyBy('id');

        return Course::query()
            ->when($courseType !== null, fn ($query) => $query->where('course_type', $courseType))
            ->orderBy('title')
            ->get()
            ->map(function (Course $course) use ($subjects): array {
                $subject = $subjects->get($course->subject_id);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'title_dv' => $course->title_dv,
                    'title_ar' => $course->title_ar,
                    'slug' => $course->slug,
                    'subject_id' => $course->subject_id,
                    'subject_name' => $subject['name_en'] ?? '',
                    'language' => $course->language,
                    // SPEC §26: what unlock rule this course runs under. The
                    // resolver owns the default, so the screen shows the mode
                    // actually in force rather than a blank for "unset".
                    'unlock_mode' => app(ResolveCourseUnlockModeAction::class)->execute($course)->value,
                    'course_type' => $course->course_type,
                    'workflow_status' => $course->workflow_status?->value ?? $course->workflow_status,
                    'marketing_status' => $course->status,
                ];
            })->values();
    }
}
