<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Offerings\Enums\DeliveryMode;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('creates offerings for each delivery mode and a default self-learning offering on publish', function () {
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Offering Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
        'created_by' => $admin->id,
    ]);

    expect(CourseOffering::query()->count())->toBe(0);

    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $self = CourseOffering::query()->where('course_id', $course->id)->first();
    expect($self?->delivery_mode)->toBe(DeliveryMode::SelfLearning)
        ->and($self?->status->value)->toBe('open')
        ->and($self?->pin_mode)->toBe('latest');

    $live = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Evening live',
        'delivery_mode' => 'live_online',
        'status' => 'open',
        'created_by' => $admin->id,
    ]);
    expect($live->delivery_mode)->toBe(DeliveryMode::LiveOnline);

    foreach (['face_to_face', 'blended', 'hybrid'] as $mode) {
        app(SaveCourseOfferingAction::class)->execute([
            'course_id' => $course->id,
            'title' => $mode,
            'delivery_mode' => $mode,
        ]);
    }

    expect(CourseOffering::query()->count())->toBe(5);

    expect(fn () => app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Bad',
        'delivery_mode' => 'correspondence',
    ]))->toThrow(ValidationException::class);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.offerings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Offerings/Catalog/Index')->has('rows', 5));
});
