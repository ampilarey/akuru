<?php

use App\Domains\Courses\Actions\DeleteContentBlockAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ReorderContentBlocksAction;
use App\Domains\Courses\Actions\ResolvePublishedLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\LessonRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeEngineLesson(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Revision Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Unit 1',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Haraka',
        'created_by' => $admin->id,
    ]);
    $first = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Fatha is a short a.'],
        'created_by' => $admin->id,
    ]);
    $second = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Kasra is a short i.'],
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'course', 'module', 'lesson', 'first', 'second');
}

it('publishes an immutable snapshot the player reads', function () {
    ['admin' => $admin, 'lesson' => $lesson, 'first' => $first] = makeEngineLesson();

    expect(app(ResolvePublishedLessonAction::class)->execute($lesson->id))->toBeNull();

    $revision = app(PublishLessonAction::class)->execute($lesson, $admin->id);
    $published = app(ResolvePublishedLessonAction::class)->execute($lesson->id);

    expect($published['blocks'][0]['data']['body'])->toBe('Fatha is a short a.')
        ->and($revision->revision_number)->toBe(1);

    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Edited draft body'],
    ], $first);
    app(DeleteContentBlockAction::class)->execute($lesson->blocks()->orderBy('position')->get()->last());
    app(ReorderContentBlocksAction::class)->execute($lesson->id, $lesson->blocks()->pluck('id')->reverse()->all());

    $still = app(ResolvePublishedLessonAction::class)->execute($lesson->id);
    expect($still['blocks'][0]['data']['body'])->toBe('Fatha is a short a.')
        ->and(count($still['blocks']))->toBe(2)
        ->and($still['blocks'][0]['position'])->toBe(0)
        ->and(LessonRevision::query()->find($revision->id)->snapshot_json['blocks'][0]['data']['body'])->toBe('Fatha is a short a.');

    $second = app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    expect($second->revision_number)->toBe(2)
        ->and(LessonRevision::query()->find($revision->id)->snapshot_json['blocks'][0]['data']['body'])->toBe('Fatha is a short a.')
        ->and(app(ResolvePublishedLessonAction::class)->execute($lesson->id)['revision_number'])->toBe(2);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.player.show', $lesson))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Player/Show')
            ->where('snapshot.revision_number', 2)
        );
});

it('rejects a duplicate lesson slug inside the same course', function () {
    ['module' => $module] = makeEngineLesson();

    expect(fn () => app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Haraka',
        'slug' => 'haraka',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('renders the outline for a catalog manager', function () {
    ['admin' => $admin, 'course' => $course] = makeEngineLesson();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.courses.outline', $course->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Outline')->has('modules', 1));
});
