<?php

use App\Domains\Courses\Actions\SaveCourseSubjectAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Audience;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseLevel;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('seeds the example subject tree and keeps audiences and levels admin-managed', function () {
    expect(CourseSubject::query()->whereNull('parent_id')->count())->toBe(5)
        ->and(CourseSubject::query()->where('slug', 'nahw')->value('parent_id'))
        ->toBe(CourseSubject::query()->where('slug', 'arabic')->value('id'))
        ->and(Audience::query()->where('slug', 'adults')->exists())->toBeTrue()
        ->and(CourseLevel::query()->where('slug', 'beginner')->exists())->toBeTrue();
});

it('rejects a circular subject parent', function () {
    $arabic = CourseSubject::query()->where('slug', 'arabic')->firstOrFail();
    $nahw = CourseSubject::query()->where('slug', 'nahw')->firstOrFail();

    expect(fn () => app(SaveCourseSubjectAction::class)->execute([
        'name_en' => $arabic->name_en,
        'parent_id' => $nahw->id,
    ], $arabic))->toThrow(ValidationException::class);
});

it('creates a draft course and enforces the publishing workflow', function () {
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $subject = CourseSubject::query()->where('slug', 'nahw')->firstOrFail();

    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Arabic Nahw 1',
        'subject_id' => $subject->id,
        'created_by' => $admin->id,
    ]);

    expect($course->workflow_status)->toBe(CourseWorkflowStatus::Draft)
        ->and($course->status)->toBe('closed')
        ->and($course->course_type)->toBe('general');

    expect(fn () => app(TransitionCourseWorkflowAction::class)->execute(
        $course,
        CourseWorkflowStatus::Published,
        true,
    ))->toThrow(ValidationException::class);

    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, false);
    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::InReview);

    expect(fn () => app(SaveEngineCourseAction::class)->execute([
        'title' => 'Changed',
        'subject_id' => $subject->id,
    ], $course->fresh()))->toThrow(ValidationException::class);

    $runner = actingPeopleAdmin(['courses.manage']);
    expect(fn () => app(TransitionCourseWorkflowAction::class)->execute(
        $course->fresh(),
        CourseWorkflowStatus::Published,
        false,
    ))->toThrow(ValidationException::class);

    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);
    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::Published);

    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Archived, true);
    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::Archived);

    expect(fn () => app(TransitionCourseWorkflowAction::class)->execute(
        $course->fresh(),
        CourseWorkflowStatus::Draft,
        true,
    ))->toThrow(ValidationException::class);

    expect($runner->id)->toBeInt();
});

it('exposes catalog screens to courses.manage and forbids them otherwise', function () {
    $manager = actingPeopleAdmin(['courses.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($manager)
        ->get(route('catalog.subjects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Taxonomy/Subjects')->has('rows'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($manager)
        ->post(route('catalog.courses.store'), ['title' => 'Basic Arabic', 'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id')])
        ->assertRedirect(route('catalog.courses.index'));

    expect(Course::query()->where('title', 'Basic Arabic')->value('workflow_status'))->toBe(CourseWorkflowStatus::Draft);

    $this->withoutLocalizationMiddleware()
        ->actingAs($manager)
        ->get(route('catalog.courses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Index')->has('rows'));

    $other = actingPeopleAdmin(['hr.manage']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->get(route('catalog.courses.index'))
        ->assertForbidden();
});
