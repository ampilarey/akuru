<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Offerings\Actions\DescribeOfferingAction;
use App\Domains\Offerings\Actions\GetOfferingAttendancePercentAction;
use App\Domains\Offerings\Actions\ListUpcomingSessionsForOfferingsAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Domains\Progress\Actions\ListLessonProgressAction;

/**
 * SPEC §24 "Student Dashboard" names twelve things it must show:
 *
 *   > Enrolled courses/offerings · Continue learning · Upcoming sessions ·
 *   > Current progress · Completed lessons · Pending lessons ·
 *   > Pending assessments · Scores · Attendance where applicable ·
 *   > Teacher feedback · Certificates · Access/payment status later
 *
 * Five were served. The page showed a percentage, a completed count and a
 * Continue link, and the card read `40% · 3 · active` — a bare number with no
 * unit, because the JSX interpolated `completed_lessons` with no label at all.
 *
 * Six were absent outright, and each was absent while the thing behind it
 * existed and worked:
 *
 * | §24 item | What was already built |
 * |---|---|
 * | Pending lessons | lesson counts and per-lesson progress |
 * | Pending assessments | `assessments` + attempt statuses |
 * | Scores | `ListAssessmentScoresAction`, feeding teacher reports |
 * | Attendance | `GetOfferingAttendancePercentAction` |
 * | Teacher feedback | `ReviewAttemptAction` writes it; the review screen alone read it |
 * | Certificates | §39 issues, renders and verifies them — staff-only, every route |
 *
 * That is the shape this codebase keeps producing: the engine is finished and
 * the person it was built for cannot see the result. Nothing here computes
 * anything new; it composes Actions that were already correct.
 *
 * "Access/payment status" is the one item §24 itself defers ("later") and the
 * one thing this does not add.
 */
class ListStudentDashboardAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId): array
    {
        $student = app(ResolveStudentForUserAction::class)->execute($userId);

        if ($student === null) {
            return [
                'student' => null,
                'enrollments' => [],
                'upcoming_sessions' => [],
                'certificates' => [],
            ];
        }

        $enrollments = CourseEnrollment::query()
            ->where('unified_student_id', $student['id'])
            ->whereIn('status', ['active', 'approved', 'completed'])
            ->orderByDesc('enrolled_at')
            ->get();

        return [
            'student' => $student,
            'upcoming_sessions' => app(ListUpcomingSessionsForOfferingsAction::class)->execute(
                $enrollments->pluck('course_offering_id')->filter()->all(),
            ),
            'certificates' => $this->certificates((int) $student['id']),
            'enrollments' => $enrollments->map(
                fn (CourseEnrollment $enrollment) => $this->enrollment($enrollment, (int) $student['id']),
            )->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollment(CourseEnrollment $enrollment, int $studentId): array
    {
        $course = Course::query()->find($enrollment->course_id);
        $progress = app(ListLessonProgressAction::class)->execute($enrollment->id);
        $completedIds = collect($progress)->where('status', 'completed')->pluck('lesson_id')->all();

        $continue = Lesson::query()
            ->where('course_id', $enrollment->course_id)
            ->whereNotNull('current_revision_id')
            ->with('module')
            ->get()
            ->sortBy(fn (Lesson $lesson) => sprintf('%05d-%05d', $lesson->module?->position ?? 0, $lesson->position))
            ->first(fn (Lesson $lesson) => ! in_array($lesson->id, $completedIds, true));

        $work = app(SummarizeEnrollmentWorkAction::class)->execute(
            $enrollment->course_id ? (int) $enrollment->course_id : null,
            $studentId,
            $completedIds,
        );

        return [
            'id' => $enrollment->id,
            'course_id' => $enrollment->course_id,
            'title' => $course?->title ?? 'Course',
            'status' => $enrollment->status,
            'progress_percentage' => (int) $enrollment->progress_percentage,
            'continue_lesson_id' => $continue?->id,
            'continue_title' => $continue?->title,
            // §24: "Enrolled courses/offerings". The card named the course and
            // never the offering, so a student in one of several batches could
            // not tell which one this was — the same gap the course learning
            // page had before `DescribeOfferingAction` existed.
            'offering' => $enrollment->course_offering_id
                ? app(DescribeOfferingAction::class)->execute((int) $enrollment->course_offering_id)
                : null,
            // §24 says "Attendance **where applicable**", and the Action already
            // answers null when the offering schedules no sessions — which is
            // exactly when attendance does not apply.
            'attendance_percent' => $enrollment->course_offering_id
                ? app(GetOfferingAttendancePercentAction::class)
                    ->execute((int) $enrollment->course_offering_id, $studentId)
                : null,
            ...$work,
        ];
    }

    /**
     * §24 lists "Certificates" on the dashboard. §39 issues them and every
     * route to one was staff-only, so this is the first place a student meets
     * a certificate that was awarded to them.
     *
     * Revoked rows are dropped rather than shown as revoked: a student has no
     * action to take on one, and listing it invites the question the dashboard
     * cannot answer. Staff keep the full history on the catalog screen.
     *
     * @return list<array<string, mixed>>
     */
    private function certificates(int $studentId): array
    {
        return app(ListIssuedCertificatesAction::class)
            ->execute(['student_id' => $studentId])
            ->reject(fn (array $row): bool => (bool) ($row['revoked'] ?? false))
            ->map(fn (array $row): array => [
                'id' => $row['id'],
                'certificate_number' => $row['certificate_number'],
                'template' => $row['template'],
                'course_name' => $row['course_name'],
                'offering_name' => $row['offering_name'],
                'completion_date' => $row['completion_date'],
                'grade' => $row['grade'],
                'verify_url' => $row['verify_url'],
                'downloadable' => $row['document_id'] !== null,
            ])
            ->values()
            ->all();
    }
}
