<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ResolvePublishedLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\PinOfferingContentAction;
use App\Domains\Offerings\Actions\ResolveOfferingPinAction;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function publishPinnedCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Pinned Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Unit',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'First',
        'created_by' => $admin->id,
    ]);
    $block = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Original pin body'],
        'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    return compact('admin', 'course', 'lesson', 'block');
}

it('pins current lesson revisions and keeps the player on that snapshot', function () {
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson, 'block' => $block] = publishPinnedCourse();
    $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();

    $pinned = app(PinOfferingContentAction::class)->execute($offering->id, $admin->id);
    expect($pinned->pin_mode)->toBe('pinned')
        ->and($pinned->pinned_revision_json[$lesson->id] ?? $pinned->pinned_revision_json[(string) $lesson->id] ?? null)
        ->toBe($lesson->fresh()->current_revision_id);

    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Edited later'],
    ], $block);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $latest = app(ResolvePublishedLessonAction::class)->execute($lesson->id);
    $pinnedId = app(ResolveOfferingPinAction::class)->revisionIdForLesson($offering->id, $lesson->id);
    $fromPin = app(ResolvePublishedLessonAction::class)->execute($lesson->id, $pinnedId);

    expect($latest['blocks'][0]['data']['body'])->toBe('Edited later')
        ->and($fromPin['blocks'][0]['data']['body'])->toBe('Original pin body');
});

it('enforces offering seat limits inside a lock', function () {
    ['course' => $course] = publishPinnedCourse();
    $limited = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'One seat live',
        'delivery_mode' => 'live_online',
        'status' => 'open',
        'seat_limit' => 1,
    ]);

    $firstUser = User::factory()->create();
    makeStudent(['user_id' => $firstUser->id, 'first_name' => 'One']);
    $secondUser = User::factory()->create();
    makeStudent(['user_id' => $secondUser->id, 'first_name' => 'Two']);

    $first = app(EnrollSelfLearningAction::class)->execute($firstUser->id, $course->id, $limited->id);
    expect($first->course_offering_id)->toBe($limited->id);

    expect(fn () => app(EnrollSelfLearningAction::class)->execute($secondUser->id, $course->id, $limited->id))
        ->toThrow(ValidationException::class);
});
