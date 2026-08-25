<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ResolveActivityDefinitionAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function publishActivityCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Activity Lab',
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
        'title' => 'Lesson one',
        'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Body'],
        'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Aisha']);

    return compact('admin', 'course', 'module', 'lesson', 'user', 'student');
}

function selectionPayload(int $courseId): array
{
    return [
        'course_id' => $courseId,
        'title' => 'Choose meaning',
        'pattern' => 'selection',
        'activity_type' => 'multiple_choice',
        'max_score' => 10,
        'data' => [
            'prompt' => 'Pick the right word',
            'options' => [
                ['id' => 'a', 'label' => 'Book'],
                ['id' => 'b', 'label' => 'Pen'],
            ],
            'correct_ids' => ['a'],
            'multiple' => false,
        ],
        'settings' => [
            'retakes_allowed' => true,
            'retake_limit' => 2,
            'show_correct_answer' => true,
        ],
    ];
}

it('creates each of the four activity patterns and rejects unknown ones', function () {
    ['course' => $course] = publishActivityCourse();

    $selection = app(SaveActivityAction::class)->execute(selectionPayload($course->id));
    $text = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Type it',
        'pattern' => 'text_input',
        'data' => ['prompt' => 'Type', 'acceptable' => ['kitab']],
    ]);
    $arrange = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Order',
        'pattern' => 'arrange',
        'data' => [
            'prompt' => 'Arrange',
            'items' => [['id' => '1', 'label' => 'One'], ['id' => '2', 'label' => 'Two']],
            'correct_order' => ['1', '2'],
        ],
    ]);
    $teacher = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Write',
        'pattern' => 'teacher_marked',
        'data' => ['prompt' => 'Essay', 'submission_kind' => 'written'],
    ]);

    expect($selection->pattern->value)->toBe('selection')
        ->and($text->pattern->value)->toBe('text_input')
        ->and($arrange->pattern->value)->toBe('arrange')
        ->and($teacher->pattern->value)->toBe('teacher_marked');

    expect(fn () => app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Broken',
        'pattern' => 'matching_engine',
        'data' => ['prompt' => 'no'],
    ]))->toThrow(ValidationException::class);
});

it('hides answer keys from students until a scored attempt with show_correct_answer', function () {
    ['course' => $course, 'user' => $user] = publishActivityCourse();
    $activity = app(SaveActivityAction::class)->execute(selectionPayload($course->id));

    $hidden = app(ResolveActivityDefinitionAction::class)->execute($activity->id, includeAnswerKeys: false);
    expect($hidden['data'])->not->toHaveKey('correct_ids')
        ->and($hidden['data']['options'][0]['label'])->toBe('Book');

    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.activities.show', $activity->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Activity')
            ->missing('activity.data.correct_ids')
        );
});

it('autosaves and auto-marks selection answers with a retake limit', function () {
    ['admin' => $admin, 'course' => $course, 'user' => $user] = publishActivityCourse();
    $activity = app(SaveActivityAction::class)->execute(selectionPayload($course->id));
    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.courses.activities.index', $course->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Activities')
            ->has('activities', 1)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.courses.activities.export', $course->id))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.activities.autosave', $activity->id), [
            'answers' => ['selected_ids' => ['b']],
        ])
        ->assertRedirect();

    $draft = ActivityAttempt::query()->first();
    expect($draft?->status->value)->toBe('in_progress')
        ->and($draft?->answers['selected_ids'])->toBe(['b']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.activities.submit', $activity->id), [
            'answers' => ['selected_ids' => ['a']],
        ])
        ->assertRedirect();

    $scored = ActivityAttempt::query()->first();
    expect($scored?->status->value)->toBe('scored')
        ->and($scored?->score)->toBe(10)
        ->and($scored?->attempt_number)->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.activities.show', $activity->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.data.correct_ids', ['a'])
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.activities.submit', $activity->id), [
            'answers' => ['selected_ids' => ['b']],
        ])
        ->assertRedirect();

    expect(ActivityAttempt::query()->count())->toBe(2);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.activities.submit', $activity->id), [
            'answers' => ['selected_ids' => ['a']],
        ])
        ->assertSessionHasErrors('attempt');
});

it('lets the catalog create activities over HTTP and forbids other staff', function () {
    ['course' => $course] = publishActivityCourse();
    $manager = actingPeopleAdmin(['courses.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($manager)
        ->post(route('catalog.courses.activities.store', $course->id), [
            'title' => 'HTTP selection',
            'pattern' => 'selection',
            'data' => json_encode([
                'prompt' => 'Pick',
                'options' => [['id' => 'yes', 'label' => 'Yes'], ['id' => 'no', 'label' => 'No']],
                'correct_ids' => ['yes'],
            ]),
        ])
        ->assertRedirect(route('catalog.courses.activities.index', $course->id));

    expect(Activity::query()->where('title', 'HTTP selection')->exists())->toBeTrue();

    $other = actingPeopleAdmin(['hr.manage']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->get(route('catalog.courses.activities.index', $course->id))
        ->assertForbidden();
});
