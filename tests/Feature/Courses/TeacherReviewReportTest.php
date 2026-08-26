<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function publishTeacherReportCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Review Report Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Start',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Lesson',
        'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Body'],
        'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $marked = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Write a sentence',
        'pattern' => 'teacher_marked',
        'data' => ['prompt' => 'Write', 'submission_kind' => 'written'],
        'max_score' => 10,
    ]);
    $quiz = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Choose meaning',
        'pattern' => 'selection',
        'max_score' => 10,
        'passing_score' => 8,
        'data' => [
            'prompt' => 'Pick',
            'options' => [['id' => 'a', 'label' => 'Book'], ['id' => 'b', 'label' => 'Pen']],
            'correct_ids' => ['a'],
        ],
        'settings' => [
            'retakes_allowed' => true,
            'retake_limit' => 2,
        ],
    ]);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Layla', 'last_name' => 'Hassan']);
    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    return compact('admin', 'course', 'marked', 'quiz', 'user');
}

it('shows pending reviews with student names and csv', function () {
    $ctx = publishTeacherReportCourse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['user'])
        ->post(route('learn.activities.submit', $ctx['marked']->id), [
            'answers' => ['text' => 'kitab jamil'],
        ])
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('catalog.reviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Reviews')
            ->has('rows', 1)
            ->where('rows.0.kind', 'activity')
            ->where('rows.0.student_name', 'Layla Hassan')
            ->where('rows.0.course_title', 'Review Report Lab')
            ->where('pending_count', 1)
            ->has('weaknesses', 0)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('catalog.reviews.export'));
    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toStartWith('text/csv')
        ->and($csv->streamedContent())->toContain('pending_review')
        ->and($csv->streamedContent())->toContain('Layla Hassan');
});

it('lists weakness and revision for a failed scored activity, not a passing one', function () {
    $ctx = publishTeacherReportCourse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['user'])
        ->post(route('learn.activities.submit', $ctx['quiz']->id), [
            'answers' => ['selected_ids' => ['b']],
        ])
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('catalog.reviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 0)
            ->has('weaknesses', 1)
            ->where('weaknesses.0.student_name', 'Layla Hassan')
            ->where('weaknesses.0.title', 'Choose meaning')
            ->where('weaknesses.0.score', 0)
            ->where('weaknesses.0.reason', 'Below passing score')
            ->where('weak_student_count', 1)
            ->has('revisions', 1)
            ->where('revisions.0.recommendation', 'Retry Choose meaning')
        );

    $passer = User::factory()->create();
    makeStudent(['user_id' => $passer->id, 'first_name' => 'Yusuf', 'last_name' => 'Ali']);
    app(EnrollSelfLearningAction::class)->execute($passer->id, $ctx['course']->id);
    $this->withoutLocalizationMiddleware()
        ->actingAs($passer)
        ->post(route('learn.activities.submit', $ctx['quiz']->id), [
            'answers' => ['selected_ids' => ['a']],
        ])
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('catalog.reviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('weaknesses', 1)
            ->where('weaknesses.0.student_name', 'Layla Hassan')
            ->where('weak_student_count', 1)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['admin'])
        ->get(route('catalog.reviews.export'));
    expect($csv->streamedContent())->toContain('weakness')
        ->and($csv->streamedContent())->toContain('revision')
        ->and($csv->streamedContent())->toContain('Retry Choose meaning')
        ->and($csv->streamedContent())->not->toContain('Yusuf Ali');
});

it('forbids teacher review reports without courses.manage', function () {
    $other = actingPeopleAdmin(['hr.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->get(route('catalog.reviews.index'))
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->get(route('catalog.reviews.export'))
        ->assertForbidden();
});
