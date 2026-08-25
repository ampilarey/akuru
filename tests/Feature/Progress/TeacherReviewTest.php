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
use App\Domains\Progress\Actions\ReviewAttemptAction;
use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lets a teacher score a submitted activity and shows feedback to the student', function () {
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Review Lab',
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

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Write a sentence',
        'pattern' => 'teacher_marked',
        'data' => ['prompt' => 'Write', 'submission_kind' => 'written'],
        'max_score' => 10,
    ]);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Layla']);
    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.activities.submit', $activity->id), [
            'answers' => ['text' => 'kitab jamil'],
        ])
        ->assertRedirect();

    $pending = ActivityAttempt::query()->first();
    expect($pending)->not->toBeNull()
        ->and($pending->status)->toBe(App\Domains\Progress\Enums\ActivityAttemptStatus::Submitted);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.reviews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Reviews')
            ->has('rows', 1)
            ->where('rows.0.kind', 'activity')
        );

    $attemptId = ActivityAttempt::query()->value('id');
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.reviews.store'), [
            'kind' => 'activity',
            'attempt_id' => $attemptId,
            'score' => 8,
            'max_score' => 10,
            'feedback' => 'Clear handwriting.',
        ])
        ->assertRedirect(route('catalog.reviews.index'));

    expect(ActivityAttempt::query()->first()?->status->value)->toBe('scored')
        ->and(ActivityAttempt::query()->value('score'))->toBe(8)
        ->and(ActivityAttempt::query()->value('feedback'))->toBe('Clear handwriting.');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.activities.show', $activity->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempt.feedback', 'Clear handwriting.')
            ->where('attempt.score', 8)
        );

    expect(app(ReviewAttemptAction::class))->toBeInstanceOf(ReviewAttemptAction::class);
});
