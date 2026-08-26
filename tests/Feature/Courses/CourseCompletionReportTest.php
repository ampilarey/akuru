<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\StartOrCompleteLessonProgressAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\RecordOfferingAttendanceAction;
use App\Domains\Offerings\Actions\SaveOfferingSessionAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Enums\GuardianRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function publishCompletionReportCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Completion Report Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'One',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Only lesson',
        'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Body'],
        'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();
    $offering->update(['academic_year_id' => $year->id, 'title' => 'Morning cohort']);
    $session = app(SaveOfferingSessionAction::class)->execute([
        'course_offering_id' => $offering->id,
        'title' => 'Required live',
        'session_type' => 'live_online',
        'starts_at' => now()->addDay(),
        'is_required' => true,
    ]);

    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Nadira', 'last_name' => 'Didi']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id, $offering->id);

    return compact('admin', 'year', 'course', 'lesson', 'offering', 'session', 'user', 'student', 'enrollment');
}

it('shows offering and course completion plus a roster csv for staff', function () {
    $ctx = publishCompletionReportCourse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('catalog.reports.completions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/CompletionReports')
            ->has('rows', 1)
            ->where('rows.0.student_name', 'Nadira Didi')
            ->where('rows.0.progress_percentage', 0)
            ->where('offering_summaries.0.enrolled', 1)
            ->where('offering_summaries.0.completed', 0)
        );

    app(StartOrCompleteLessonProgressAction::class)->execute($ctx['lesson']->id, $ctx['user'], 'completed');
    app(RecordOfferingAttendanceAction::class)->execute([
        'course_offering_session_id' => $ctx['session']->id,
        'enrollment_id' => $ctx['enrollment']->id,
        'status' => 'present',
        'attendance_mode' => 'online',
        'marked_by' => $ctx['admin']->id,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('catalog.reports.completions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.progress_percentage', 100)
            ->where('rows.0.status', 'completed')
            ->where('offering_summaries.0.completed', 1)
            ->where('course_summaries.0.title', 'Completion Report Lab')
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('catalog.reports.completions.export'));
    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toStartWith('text/csv')
        ->and($csv->streamedContent())->toContain('Nadira Didi')
        ->and($csv->streamedContent())->toContain('completed');
});

it('lets a parent and the student read performance with csv', function () {
    $ctx = publishCompletionReportCourse();
    app(StartOrCompleteLessonProgressAction::class)->execute($ctx['lesson']->id, $ctx['user'], 'completed');

    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($ctx['student'], $guardian, GuardianRelationship::Father, true);
    $guardianUser = User::query()->findOrFail($guardian->user_id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.performance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Performance')
            ->has('students', 1)
            ->where('students.0.name', 'Nadira Didi')
            ->where('students.0.rows.0.course_title', 'Completion Report Lab')
            ->where('students.0.rows.0.progress_percentage', 50)
        );

    $parentCsv = $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.performance.export'));
    $parentCsv->assertOk();
    expect($parentCsv->streamedContent())->toContain('Nadira Didi');

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['user'])
        ->get(route('portal.performance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('students', 1)
            ->where('students.0.relationship', 'self')
            ->where('students.0.rows.0.progress_percentage', 50)
        );
});

it('forbids completion reports without courses.manage', function () {
    $other = actingPeopleAdmin(['hr.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->get(route('catalog.reports.completions'))
        ->assertForbidden();
});
