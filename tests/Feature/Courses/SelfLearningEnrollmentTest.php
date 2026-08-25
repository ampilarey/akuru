<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Models\StudentLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function publishSelfLearnCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Self Nahw',
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
    $preview = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Welcome',
        'is_preview' => true,
        'created_by' => $admin->id,
    ]);
    $first = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Lesson one',
        'created_by' => $admin->id,
    ]);
    $second = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Lesson two',
        'created_by' => $admin->id,
    ]);
    foreach ([$preview, $first, $second] as $lesson) {
        app(SaveContentBlockAction::class)->execute([
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'data' => ['body' => $lesson->title],
            'created_by' => $admin->id,
        ]);
        app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    }

    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Yusuf']);

    return compact('admin', 'course', 'preview', 'first', 'second', 'user', 'student');
}

it('enrolls a student in a published course and rejects drafts', function () {
    ['course' => $course, 'user' => $user] = publishSelfLearnCourse();

    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    expect($enrollment->status)->toBe('active')
        ->and($enrollment->enrollment_type)->toBe('free')
        ->and($enrollment->payment_status)->toBe('not_required')
        ->and($enrollment->unified_student_id)->toBeInt()
        ->and($enrollment->student_id)->toBeInt()
        ->and(app(EnrollSelfLearningAction::class)->execute($user->id, $course->id)->id)->toBe($enrollment->id);

    $draft = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Still draft',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
    ]);

    expect(fn () => app(EnrollSelfLearningAction::class)->execute($user->id, $draft->id))
        ->toThrow(ValidationException::class);
});

it('lets enrolled students play and complete lessons while locking the next one', function () {
    ['course' => $course, 'preview' => $preview, 'first' => $first, 'second' => $second, 'user' => $user] = publishSelfLearnCourse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.lessons.show', $preview))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Player/Show'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.lessons.show', $first))
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.courses.enroll', $course->id))
        ->assertRedirect(route('learn.courses.show', $course->id));

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.lessons.show', $first))
        ->assertOk();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.lessons.show', $second))
        ->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.lessons.complete', $first))
        ->assertRedirect(route('learn.lessons.show', $first));

    $progress = StudentLessonProgress::query()->where('lesson_id', $first->id)->first();
    expect($progress?->lesson_revision_id)->toBe($first->fresh()->current_revision_id)
        ->and($progress?->status->value)->toBe('completed');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.lessons.show', $second))
        ->assertOk();

    expect(CourseEnrollment::query()->where('course_id', $course->id)->value('progress_percentage'))->toBe(50);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Dashboard')
            ->has('enrollments', 1)
        );
});
