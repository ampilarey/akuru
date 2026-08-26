<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Offerings\Actions\GetOfferingAttendancePercentAction;
use App\Domains\Progress\Actions\ListLessonProgressAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildEnrollmentReportRowsAction
{
    /**
     * @param  Collection<int, CourseEnrollment>|iterable<CourseEnrollment>  $enrollments
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(iterable $enrollments): Collection
    {
        $enrollments = collect($enrollments);
        if ($enrollments->isEmpty()) {
            return collect();
        }

        $students = DB::table('students')
            ->whereIn('id', $enrollments->pluck('unified_student_id')->filter()->all() ?: [0])
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');
        $courses = Course::query()
            ->whereIn('id', $enrollments->pluck('course_id')->all() ?: [0])
            ->get(['id', 'title'])
            ->keyBy('id');
        $offerings = DB::table('course_offerings')
            ->whereIn('id', $enrollments->pluck('course_offering_id')->filter()->all() ?: [0])
            ->get(['id', 'title', 'academic_year_id', 'course_id'])
            ->keyBy('id');

        $requiredByCourse = [];

        return $enrollments->map(function (CourseEnrollment $enrollment) use ($students, $courses, $offerings, &$requiredByCourse): array {
            $courseId = (int) $enrollment->course_id;
            if (! isset($requiredByCourse[$courseId])) {
                $requiredByCourse[$courseId] = count(app(AuthorizeLessonAccessAction::class)->requiredLessons($courseId));
            }
            $completedLessons = collect(app(ListLessonProgressAction::class)->execute($enrollment->id))
                ->where('status', 'completed')
                ->count();
            $offeringId = $enrollment->course_offering_id ? (int) $enrollment->course_offering_id : null;
            $attendance = $offeringId && $enrollment->unified_student_id
                ? app(GetOfferingAttendancePercentAction::class)->execute($offeringId, (int) $enrollment->unified_student_id)
                : null;
            $student = $students->get($enrollment->unified_student_id);
            $offering = $offeringId ? $offerings->get($offeringId) : null;

            return [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->unified_student_id ? (int) $enrollment->unified_student_id : null,
                'student_name' => $student ? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) : '',
                'course_id' => $courseId,
                'course_title' => (string) ($courses->get($courseId)?->title ?? ''),
                'offering_id' => $offeringId,
                'offering_title' => $offering ? (string) $offering->title : '',
                'academic_year_id' => $offering?->academic_year_id ? (int) $offering->academic_year_id : null,
                'progress_percentage' => (int) $enrollment->progress_percentage,
                'attendance_percent' => $attendance,
                'lessons_completed' => $completedLessons,
                'lessons_required' => $requiredByCourse[$courseId],
                'status' => (string) $enrollment->status,
                'completed_at' => $enrollment->completed_at?->toDateString(),
                'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
            ];
        })->values();
    }
}
