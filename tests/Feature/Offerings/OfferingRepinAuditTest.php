<?php

use App\Domains\Offerings\Actions\ListOfferingRepinEventsAction;
use App\Domains\Offerings\Actions\PinOfferingContentAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Offerings\Models\OfferingRepinEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * SPEC §28.4 "Offering Pinning":
 *
 *   > Admins may explicitly re-pin an offering to a newer course content
 *   > version, but this must be a deliberate action.
 *   >
 *   > Offering re-pinning must never happen automatically.
 *   >
 *   > If an offering is re-pinned, the system should record:
 *   > Old pinned version · New pinned version · Admin who changed it ·
 *   > Timestamp · Reason/comment nullable
 *
 * `PinOfferingContentAction` overwrote `pinned_revision_json` in place. The
 * offering kept `pinned_by`/`pinned_at` for the *latest* pin only, so the
 * previous version was gone the moment it was replaced — and re-pinning
 * changes what enrolled students see mid-offering, which is precisely why
 * §28.4 asks for it to be deliberate and traceable.
 */
uses(RefreshDatabase::class);

function repinFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(\App\Domains\Courses\Actions\SaveEngineCourseAction::class)->execute([
        'title' => 'Pinned course '.uniqid(),
        'subject_id' => \App\Domains\Courses\Models\CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(\App\Domains\Courses\Actions\SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit', 'created_by' => $admin->id,
    ]);
    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Term 1 offering',
        'slug' => 'term-1-offering-'.\Illuminate\Support\Str::random(6),
        'delivery_mode' => 'self_learning',
        'status' => 'open',
        'pin_mode' => 'latest',
    ]);

    return compact('admin', 'course', 'module', 'offering');
}

function publishRepinLesson(int $moduleId, string $title, int $adminId): \App\Domains\Courses\Models\Lesson
{
    $lesson = app(\App\Domains\Courses\Actions\SaveLessonAction::class)->execute([
        'course_module_id' => $moduleId, 'title' => $title, 'created_by' => $adminId,
    ]);
    app(\App\Domains\Courses\Actions\SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text',
        'data' => ['body' => 'Body for '.$title], 'settings' => ['direction' => 'auto'],
    ]);
    app(\App\Domains\Courses\Actions\PublishLessonAction::class)->execute($lesson, $adminId);

    return $lesson->refresh();
}

it('records the first pin, including that there was no previous version', function () {
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    publishRepinLesson($module->id, 'Lesson one', $admin->id);

    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id, 'Opening the term');

    $event = OfferingRepinEvent::query()->firstOrFail();

    expect($event->old_pinned_revision_json)->toBeNull()
        ->and($event->old_pin_mode)->toBe('latest')
        ->and($event->new_pin_mode)->toBe('pinned')
        ->and($event->changed_by)->toBe($admin->id)
        ->and($event->reason)->toBe('Opening the term')
        ->and($event->new_pinned_revision_json)->toHaveCount(1);
});

it('keeps the old version when re-pinning, instead of overwriting it', function () {
    // The defect in one assertion: before this, the first pin's revision map
    // was simply gone once the second pin replaced it.
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    $lesson = publishRepinLesson($module->id, 'Lesson one', $admin->id);

    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id);
    $firstRevision = $lesson->fresh()->current_revision_id;

    // Publish again: the lesson now points at a newer revision.
    app(\App\Domains\Courses\Actions\PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id, 'Corrected a typo');

    $events = OfferingRepinEvent::query()->orderBy('id')->get();

    expect($events)->toHaveCount(2)
        ->and($events[1]->old_pinned_revision_json[$lesson->id])->toBe($firstRevision)
        ->and($events[1]->new_pinned_revision_json[$lesson->id])->not->toBe($firstRevision);
});

it('accepts a pin with no reason, as §28.4 allows', function () {
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    publishRepinLesson($module->id, 'Lesson one', $admin->id);

    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id);

    expect(OfferingRepinEvent::query()->value('reason'))->toBeNull();
});

it('treats a blank reason as no reason rather than storing whitespace', function () {
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    publishRepinLesson($module->id, 'Lesson one', $admin->id);

    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id, '   ');

    expect(OfferingRepinEvent::query()->value('reason'))->toBeNull();
});

it('carries the offering academic year, not today (rule 10)', function () {
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    $yearId = \Illuminate\Support\Facades\DB::table('academic_years')->value('id');
    $offering->update(['academic_year_id' => $yearId]);
    publishRepinLesson($module->id, 'Lesson one', $admin->id);

    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id);

    expect(OfferingRepinEvent::query()->value('academic_year_id'))->toBe($yearId);
});

it('still pins the offering itself', function () {
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    $lesson = publishRepinLesson($module->id, 'Lesson one', $admin->id);

    $pinned = app(PinOfferingContentAction::class)->execute($offering->id, $admin->id);

    expect($pinned->pin_mode)->toBe('pinned')
        ->and($pinned->pinned_revision_json[$lesson->id])->toBe($lesson->fresh()->current_revision_id)
        ->and($pinned->pinned_by)->toBe($admin->id);
});

it('reports which lessons actually moved, not every lesson', function () {
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    $moved = publishRepinLesson($module->id, 'Changed lesson', $admin->id);
    $untouched = publishRepinLesson($module->id, 'Untouched lesson', $admin->id);

    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id);
    app(\App\Domains\Courses\Actions\PublishLessonAction::class)->execute($moved->fresh(), $admin->id);
    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id);

    $listed = app(ListOfferingRepinEventsAction::class)->execute($offering->id);

    // Newest first, so [0] is the second pin.
    expect($listed[0]['changed_lessons'])->toBe([$moved->id])
        ->and($listed[0]['changed_lessons'])->not->toContain($untouched->id);
});

it('reports a re-pin that changed nothing as changing nothing', function () {
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    publishRepinLesson($module->id, 'Lesson one', $admin->id);

    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id);
    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id);

    // A pin that re-pins the same revisions must read as a no-op rather than
    // as "every lesson changed".
    expect(app(ListOfferingRepinEventsAction::class)->execute($offering->id)[0]['changed_lessons'])->toBe([]);
});

it('names the admin who changed it', function () {
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    publishRepinLesson($module->id, 'Lesson one', $admin->id);

    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id, 'Because');

    $listed = app(ListOfferingRepinEventsAction::class)->execute($offering->id);

    expect($listed[0]['changed_by'])->toBe($admin->name)
        ->and($listed[0]['reason'])->toBe('Because')
        ->and($listed[0]['changed_at'])->not->toBeNull();
});

it('captures the reason through the route', function () {
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    publishRepinLesson($module->id, 'Lesson one', $admin->id);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/offerings/{$offering->id}/pin", ['reason' => 'Syllabus update approved'])
        ->assertRedirect();

    expect(OfferingRepinEvent::query()->value('reason'))->toBe('Syllabus update approved');
});

it('refuses to pin without courses.manage', function () {
    ['module' => $module, 'offering' => $offering, 'admin' => $admin] = repinFixture();
    publishRepinLesson($module->id, 'Lesson one', $admin->id);
    $outsider = actingPeopleAdmin([]);

    $this->actingAs($outsider)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/offerings/{$offering->id}/pin", ['reason' => 'nope'])
        ->assertForbidden();

    expect(OfferingRepinEvent::query()->count())->toBe(0);
});

it('sends the audit trail to the offerings screen', function () {
    // Written but never shown is not an audit trail.
    ['admin' => $admin, 'module' => $module, 'offering' => $offering] = repinFixture();
    publishRepinLesson($module->id, 'Lesson one', $admin->id);
    app(PinOfferingContentAction::class)->execute($offering->id, $admin->id, 'Visible reason');

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/offerings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'rows.0.repin_events.0.reason',
            'Visible reason',
        ));
});
