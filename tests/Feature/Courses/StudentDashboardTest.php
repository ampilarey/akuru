<?php

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Courses\Actions\AttachAssessmentQuestionAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\IssueCertificateAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Actions\SaveCertificateTemplateAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\IssuedCertificate;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Actions\ReviewAttemptAction;
use App\Domains\Progress\Actions\StartAssessmentAttemptAction;
use App\Domains\Progress\Actions\SubmitAssessmentAttemptAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §24 "Student Dashboard" names twelve things the page must show:
 *
 *   > Enrolled courses/offerings · Continue learning · Upcoming sessions ·
 *   > Current progress · Completed lessons · Pending lessons ·
 *   > Pending assessments · Scores · Attendance where applicable ·
 *   > Teacher feedback · Certificates · Access/payment status later
 *
 * **Five were served.** The card rendered `40% · 3 · active` — the middle
 * figure is `completed_lessons` interpolated with no label, and since nothing
 * sent a total, "how much is left?" was both unlabelled and unanswerable.
 *
 * Six were absent, and every one of them was absent while the machinery behind
 * it existed and worked:
 *
 * - **Scores** — `ListAssessmentScoresAction` was already feeding teacher
 *   reports.
 * - **Teacher feedback** — `ReviewAttemptAction` has been storing comments
 *   since the review slice, and only the teacher's own screen read them back.
 *   A comment written *to* a student was visible on the assessment page and
 *   nowhere they would think to look.
 * - **Attendance** — `GetOfferingAttendancePercentAction` existed, including
 *   its null answer for "no sessions scheduled", which is §24's "where
 *   applicable".
 * - **Certificates** — §39 issues, renders, numbers and verifies them. **Every
 *   route to one was staff-only**, so the system awarded a student a
 *   certificate, printed its number on the course page, and gave them no way
 *   to open it.
 *
 * The only item not added is the one §24 itself defers: "Access/payment status
 * later".
 */
uses(RefreshDatabase::class);

function dashboardCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Dashboard course '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit 1', 'created_by' => $admin->id,
    ]);
    // Publishing matters: a lesson with no current revision is not one a
    // student can be asked to do, so it must not be counted as owed either.
    $lessons = collect(['Lesson one', 'Lesson two', 'Lesson three'])->map(function (string $title) use ($module, $admin) {
        $lesson = app(SaveLessonAction::class)->execute([
            'course_module_id' => $module->id, 'title' => $title, 'created_by' => $admin->id,
        ]);
        app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

        return $lesson->fresh();
    });

    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return ['admin' => $admin, 'course' => $course->fresh(), 'module' => $module, 'lessons' => $lessons];
}

/**
 * One ten-point question on the assessment.
 *
 * Not decoration: an attempt over an empty assessment scores out of 1, because
 * `ScoreAssessmentSnapshotsAction` floors `max_score` at 1 and
 * `ReviewAttemptAction` then clamps the teacher's mark to it. A fixture without
 * a question would have the dashboard showing 1/1 for an "8 out of 10".
 */
function dashboardTenPointQuestion(int $assessmentId): void
{
    $question = app(SaveQuestionAction::class)->execute([
        'question_type' => 'essay',
        'question_text' => 'Explain your answer.',
    ]);
    app(AttachAssessmentQuestionAction::class)->execute([
        'assessment_id' => $assessmentId,
        'question_id' => $question->id,
        'points_override' => 10,
    ]);
}

function dashboardStudent(int $courseId): array
{
    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Dash', 'last_name' => 'Pupil']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $courseId, null);

    return compact('user', 'student', 'enrollment');
}

it('counts the lessons a student still owes, with a total to read it against', function () {
    // `40% · 3 · active` — the 3 was `completed_lessons` with no label and no
    // denominator anywhere in the payload.
    ['course' => $course] = dashboardCourse();
    ['user' => $user] = dashboardStudent($course->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Dashboard')
            ->where('enrollments.0.total_lessons', 3)
            ->where('enrollments.0.completed_lessons', 0)
            ->where('enrollments.0.pending_lessons', 3));
});

it('counts pending assessments but not one already waiting on a teacher', function () {
    ['admin' => $admin, 'course' => $course] = dashboardCourse();
    ['user' => $user, 'student' => $student, 'enrollment' => $enrollment] = dashboardStudent($course->id);

    $untouched = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Never opened', 'status' => 'published',
    ]);
    $submitted = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Handed in', 'status' => 'published',
    ]);
    // A draft assessment is not work a student owes.
    app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Still a draft', 'status' => 'draft',
    ]);

    app(StartAssessmentAttemptAction::class)->execute($submitted->id, $enrollment->id, $student->id, $course->id);
    app(SubmitAssessmentAttemptAction::class)->execute($submitted->id, $enrollment->id, [], $student->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Only the untouched one is the student's to chase; a submitted
            // attempt is on the teacher's desk, and a draft is on nobody's.
            ->where('enrollments.0.pending_assessments', 1)
            ->has('enrollments.0.assessments', 2));

    expect($untouched->status->value)->toBe('published');
});

it('shows the mark and the teacher comment written on it', function () {
    ['admin' => $admin, 'course' => $course] = dashboardCourse();
    ['user' => $user, 'student' => $student, 'enrollment' => $enrollment] = dashboardStudent($course->id);

    $assessment = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit test', 'status' => 'published', 'max_score' => 10,
    ]);
    dashboardTenPointQuestion((int) $assessment->id);
    $attempt = app(StartAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, $student->id, $course->id);
    app(SubmitAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, [], $student->id);

    app(ReviewAttemptAction::class)->execute('assessment', (int) $attempt['id'], [
        'score' => 8,
        'feedback' => 'Good work — watch the third answer.',
    ], $admin->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollments.0.assessments.0.score', 8)
            ->where('enrollments.0.assessments.0.status', 'scored')
            // §24 "Teacher feedback": stored since the review slice, read back
            // only by the teacher's own screen until now.
            ->has('enrollments.0.feedback', 1)
            ->where('enrollments.0.feedback.0.feedback', 'Good work — watch the third answer.'));
});

it('honours §19 show_results on the dashboard as well as the assessment page', function () {
    // The §19 slice fixed a teacher's unpublished marks showing anyway. Adding
    // scores one screen further out is exactly how that gets undone.
    ['admin' => $admin, 'course' => $course] = dashboardCourse();
    ['user' => $user, 'student' => $student, 'enrollment' => $enrollment] = dashboardStudent($course->id);

    $assessment = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Held back',
        'status' => 'published',
        'max_score' => 10,
        'show_results' => false,
    ]);
    dashboardTenPointQuestion((int) $assessment->id);
    $attempt = app(StartAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, $student->id, $course->id);
    app(SubmitAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, [], $student->id);
    app(ReviewAttemptAction::class)->execute('assessment', (int) $attempt['id'], [
        'score' => 9,
        'feedback' => 'Discussed in class.',
    ], $admin->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollments.0.assessments.0.score', null)
            ->where('enrollments.0.assessments.0.show_results', false)
            // Feedback is deliberately not hidden with the mark: §19's switch
            // is about the score, and a comment was written to be read.
            ->where('enrollments.0.feedback.0.feedback', 'Discussed in class.'));
});

it('lists a certificate the student earned and lets them open it', function () {
    ['admin' => $admin, 'course' => $course] = dashboardCourse();
    ['user' => $user, 'student' => $student, 'enrollment' => $enrollment] = dashboardStudent($course->id);

    $template = app(SaveCertificateTemplateAction::class)->execute([
        'name' => 'Course completion',
        'kind' => 'course_completion',
        'course_id' => $course->id,
        'active' => true,
        'rules' => ['min_progress_percent' => 0],
        'created_by' => $admin->id,
    ]);

    $issued = app(IssueCertificateAction::class)->execute([
        'certificate_template_id' => $template->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'enrollment_id' => $enrollment->id,
        'academic_year_id' => (AcademicYear::query()->first() ?? makeYear(['name' => 'Cert '.uniqueFixtureSuffix()]))->id,
        'issued_by' => $admin->id,
    ]);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('certificates', 1)
            ->where('certificates.0.certificate_number', $issued->certificate_number));

    // Every §39 route sat behind `courses.manage`. The person named on the
    // certificate could not open it.
    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.certificates.show', $issued->id))
        ->assertOk();
});

it('refuses someone else certificate, and a revoked one', function () {
    ['admin' => $admin, 'course' => $course] = dashboardCourse();
    ['student' => $student, 'enrollment' => $enrollment] = dashboardStudent($course->id);
    ['user' => $stranger] = dashboardStudent($course->id);

    $template = app(SaveCertificateTemplateAction::class)->execute([
        'name' => 'Course completion',
        'kind' => 'course_completion',
        'course_id' => $course->id,
        'active' => true,
        'rules' => ['min_progress_percent' => 0],
        'created_by' => $admin->id,
    ]);
    $issued = app(IssueCertificateAction::class)->execute([
        'certificate_template_id' => $template->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'enrollment_id' => $enrollment->id,
        'academic_year_id' => (AcademicYear::query()->first() ?? makeYear(['name' => 'Cert '.uniqueFixtureSuffix()]))->id,
        'issued_by' => $admin->id,
    ]);

    // Not-found rather than forbidden: a 403 confirms the id exists and belongs
    // to somebody.
    $this->actingAs($stranger)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.certificates.show', $issued->id))
        ->assertNotFound();

    IssuedCertificate::query()->whereKey($issued->id)->update(['revoked_at' => now()]);

    $owner = User::query()->find($student->user_id);
    $this->actingAs($owner)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.certificates.show', $issued->id))
        ->assertNotFound();

    // A revoked certificate also leaves the dashboard: the student has no
    // action to take on it.
    $this->actingAs($owner)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('certificates', 0));
});
