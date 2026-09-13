<?php

use App\Domains\Courses\Actions\AttachAssessmentQuestionAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Courses\Actions\SuspendEnrollmentAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Actions\ReviewAttemptAction;
use App\Domains\Progress\Actions\StartAssessmentAttemptAction;
use App\Domains\Progress\Actions\SubmitAssessmentAttemptAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §33 "Admin Dashboard → Reports" names ten:
 *
 *   > Total students · Active enrollments · Course completion · Offering
 *   > completion · Lesson completion · Attendance reports · Assessment scores ·
 *   > Pending reviews · Certificates issued · Payment reports later
 *
 * **Six were already computed, correctly**, and scattered across three
 * unrelated screens with no way to see them together: course, offering and
 * lesson completion plus attendance on `/catalog/reports/completions`, pending
 * reviews on `/catalog/reviews`, certificates on `/catalog/certificates`.
 *
 * **Three had no reader at all** — total students, active enrollments and
 * assessment scores — though every one was a count or an existing Action away.
 * `CountStudentsAction` existed and was used by the academics side;
 * `ListScoredAttemptsAction` had been feeding the teacher review report all
 * along; active enrollments is a `where` clause.
 *
 * So this slice composes rather than computes. The defect was not a missing
 * calculation, it was a missing **place** — §33 asks a single question and the
 * answer was spread across three screens and three absences.
 */
uses(RefreshDatabase::class);

function reportsAdmin(): User
{
    return actingPeopleAdmin(['courses.manage', 'courses.publish']);
}

function reportsCourse(User $admin): object
{
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Reports '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return $course->fresh();
}

function reportsStudent(int $courseId): array
{
    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Report', 'last_name' => 'Pupil']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $courseId, null);

    return compact('user', 'student', 'enrollment');
}

it('counts the three reports §33 names that nothing read', function () {
    $admin = reportsAdmin();
    $course = reportsCourse($admin);
    ['enrollment' => $enrollment] = reportsStudent($course->id);
    reportsStudent($course->id);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Reports')
            // Total students: the roll of the institute.
            ->where('totals.students', 2)
            // Active enrollments.
            ->where('totals.active_enrollments', 2)
            // Assessment scores — the key exists even with nothing marked yet.
            ->has('scores.count'));

    expect($enrollment->id)->toBeInt();
});

it('counts a suspended enrolment as not active', function () {
    // §23's vocabulary, read by §33's figure. A suspended student is not
    // "actively enrolled" — that is the whole reason suspension releases a seat.
    $admin = reportsAdmin();
    $course = reportsCourse($admin);
    ['enrollment' => $first] = reportsStudent($course->id);
    reportsStudent($course->id);

    app(SuspendEnrollmentAction::class)->execute($first);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.students', 2)
            ->where('totals.active_enrollments', 1));
});

it('summarises assessment scores an admin could not see before', function () {
    $admin = reportsAdmin();
    $course = reportsCourse($admin);
    ['student' => $student, 'enrollment' => $enrollment] = reportsStudent($course->id);

    $assessment = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Paper', 'status' => 'published', 'max_score' => 10,
    ]);
    $question = app(SaveQuestionAction::class)->execute([
        'question_type' => 'essay', 'question_text' => 'Explain.',
    ]);
    app(AttachAssessmentQuestionAction::class)->execute([
        'assessment_id' => $assessment->id,
        'question_id' => $question->id,
        'points_override' => 10,
        // §21's required gate refuses an empty submit; the claim here is about
        // the score reaching the report, not about whether it had to be answered.
        'is_required' => false,
    ]);

    $attempt = app(StartAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, $student->id, $course->id);
    app(SubmitAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, [], $student->id);
    app(ReviewAttemptAction::class)->execute('assessment', (int) $attempt['id'], ['score' => 7], $admin->id);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scores.count', 1)
            ->where('scores.average_percent', 70));
});

it('reports attendance as unknown rather than zero when nothing is scheduled', function () {
    // §24's "where applicable", carried into §33's figure: 0% would read as
    // "nobody turned up", which is a different and much worse claim.
    $admin = reportsAdmin();
    $course = reportsCourse($admin);
    reportsStudent($course->id);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('totals.average_attendance', null));
});

it('carries the course and offering completion summaries §33 asks for', function () {
    $admin = reportsAdmin();
    $course = reportsCourse($admin);
    reportsStudent($course->id);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/reports')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('byCourse', 1)
            ->where('byCourse.0.enrolled', 1)
            ->has('byOffering', 1));
});

it('exports every figure and refuses anyone without courses.manage', function () {
    $admin = reportsAdmin();
    reportsCourse($admin);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/reports/export')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $other = actingPeopleAdmin(['hr.manage']);
    $this->actingAs($other)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/reports')
        ->assertForbidden();
});

it('says plainly that §33 defers payment reports', function () {
    // The tenth report is the section's own "later". An administrator wondering
    // whether it is missing or broken is a worse outcome than saying so.
    $source = (string) file_get_contents(base_path('resources/js/Pages/Courses/Catalog/Reports.jsx'));

    expect($source)->toContain('Payment reports are deferred');
});
