<?php

use App\Domains\Courses\Actions\PublishCourseModuleAction;
use App\Domains\Courses\Actions\ReorderCourseModulesAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Enums\ModuleStatus;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §12 "Modules / Sections" lists five things a course creator must be
 * able to do:
 *
 *   > - Create modules
 *   > - Edit modules
 *   > - Delete draft modules if safe
 *   > - Reorder modules
 *   > - Publish/unpublish modules depending on permissions
 *
 * **Two of the five worked.** There was a create route and a delete route and
 * nothing in between: no edit, no reorder, no way to change a status.
 *
 * The status one is the worst of the three, because the column looked
 * implemented. `course_modules.status` is `varchar(20) NOT NULL DEFAULT
 * 'draft'`, `SaveCourseModuleAction` read `$data['status'] ?? 'draft'`, and
 * **no caller ever passed one** — the controller validated `title` and
 * `description` only. So every module ever created was permanently draft, and
 * `DeleteCourseModuleAction`'s refusal ("Only a draft module can be deleted")
 * named a condition nothing could fail.
 *
 * `position` was the same shape: assigned once at creation as
 * `max(position) + 1`, never changed. An author who added Unit 3 before
 * noticing Unit 2 was missing could not fix the order — and §12's delete only
 * works on an empty draft module, so once a module had a lesson its place was
 * frozen.
 */
uses(RefreshDatabase::class);

/**
 * Named distinctly because Pest loads every test file into one scope, so a
 * bare `moduleCourse()` collides with `DeleteCourseModuleTest`'s helper of the
 * same name — a fatal that only appears when the whole suite runs, not when
 * this file runs alone.
 */
function moduleMgmtCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Modules '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'course');
}

function makeMgmtModule(int $courseId, string $title, int $adminId): CourseModule
{
    return app(SaveCourseModuleAction::class)->execute([
        'course_id' => $courseId, 'title' => $title, 'created_by' => $adminId,
    ]);
}

it('lets a course creator edit a module', function () {
    // §12 "Edit modules". A typo in a heading every student sees was permanent
    // unless the module happened to be empty and could be deleted.
    ['admin' => $admin, 'course' => $course] = moduleMgmtCourse();
    $module = makeMgmtModule($course->id, 'Untit 1', $admin->id);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->put("/catalog/courses/{$course->id}/modules/{$module->id}", [
            'title' => 'Unit 1',
            'description' => 'Foundations',
        ])
        ->assertRedirect();

    expect($module->refresh()->title)->toBe('Unit 1')
        ->and($module->description)->toBe('Foundations');
});

it('lets a course creator reorder modules', function () {
    // §12 "Reorder modules". `position` was assigned once and never changed.
    ['admin' => $admin, 'course' => $course] = moduleMgmtCourse();
    $a = makeMgmtModule($course->id, 'Unit A', $admin->id);
    $b = makeMgmtModule($course->id, 'Unit B', $admin->id);
    $c = makeMgmtModule($course->id, 'Unit C', $admin->id);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/modules/reorder", [
            'order' => [$c->id, $a->id, $b->id],
        ])
        ->assertRedirect();

    expect(CourseModule::query()->where('course_id', $course->id)->orderBy('position')->pluck('id')->all())
        ->toBe([$c->id, $a->id, $b->id]);
});

it('refuses a reorder that does not name every module exactly once', function () {
    // The §16 lesson applied here: a partial list would renumber some rows and
    // leave the rest colliding.
    ['admin' => $admin, 'course' => $course] = moduleMgmtCourse();
    $a = makeMgmtModule($course->id, 'Unit A', $admin->id);
    makeMgmtModule($course->id, 'Unit B', $admin->id);

    expect(fn () => app(ReorderCourseModulesAction::class)->execute($course->id, [$a->id]))
        ->toThrow(ValidationException::class);
});

it('refuses a reorder naming a module from another course', function () {
    ['admin' => $admin, 'course' => $course] = moduleMgmtCourse();
    ['course' => $other] = moduleMgmtCourse();
    $mine = makeMgmtModule($course->id, 'Mine', $admin->id);
    $theirs = makeMgmtModule($other->id, 'Theirs', $admin->id);

    expect(fn () => app(ReorderCourseModulesAction::class)->execute($course->id, [$mine->id, $theirs->id]))
        ->toThrow(ValidationException::class);
});

it('makes the status column mean something', function () {
    // The defect: nothing anywhere wrote this, so every module was
    // permanently draft and the column was decoration.
    ['admin' => $admin, 'course' => $course] = moduleMgmtCourse();
    $module = makeMgmtModule($course->id, 'Unit 1', $admin->id);
    app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Lesson', 'created_by' => $admin->id,
    ]);

    expect($module->refresh()->status)->toBe(ModuleStatus::Draft);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/modules/{$module->id}/status", ['status' => 'published'])
        ->assertRedirect();

    expect($module->refresh()->status)->toBe(ModuleStatus::Published)
        ->and($module->status->isVisibleToStudents())->toBeTrue();
});

it('refuses to publish a module with nothing in it', function () {
    // A published empty module is a heading a student can open to find
    // nothing. §12's own sequence puts content before publication.
    ['admin' => $admin, 'course' => $course] = moduleMgmtCourse();
    $module = makeMgmtModule($course->id, 'Empty', $admin->id);

    expect(fn () => app(PublishCourseModuleAction::class)->execute($module, ModuleStatus::Published))
        ->toThrow(ValidationException::class);
});

it('lets a published module be taken back to draft even though it has lessons', function () {
    // Unpublishing is how an author fixes something students should not be
    // seeing. A rule that blocked it when the module has content would block
    // it exactly when it matters.
    ['admin' => $admin, 'course' => $course] = moduleMgmtCourse();
    $module = makeMgmtModule($course->id, 'Unit 1', $admin->id);
    app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Lesson', 'created_by' => $admin->id,
    ]);
    app(PublishCourseModuleAction::class)->execute($module, ModuleStatus::Published);

    expect(app(PublishCourseModuleAction::class)->execute($module->refresh(), ModuleStatus::Draft)->status)
        ->toBe(ModuleStatus::Draft);
});

it('treats staying put as a no-op, not an error', function () {
    ['admin' => $admin, 'course' => $course] = moduleMgmtCourse();
    $module = makeMgmtModule($course->id, 'Unit 1', $admin->id);

    expect(app(PublishCourseModuleAction::class)->execute($module, ModuleStatus::Draft)->status)
        ->toBe(ModuleStatus::Draft);
});

it('does not let an ordinary edit change the status in passing', function () {
    // §11.4's mistake, not repeated: a rename must not silently unpublish.
    ['admin' => $admin, 'course' => $course] = moduleMgmtCourse();
    $module = makeMgmtModule($course->id, 'Unit 1', $admin->id);
    app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Lesson', 'created_by' => $admin->id,
    ]);
    app(PublishCourseModuleAction::class)->execute($module, ModuleStatus::Published);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->put("/catalog/courses/{$course->id}/modules/{$module->id}", ['title' => 'Renamed'])
        ->assertRedirect();

    expect($module->refresh()->title)->toBe('Renamed')
        ->and($module->status)->toBe(ModuleStatus::Published);
});

it('gates publishing on the publish permission, as §12 says', function () {
    // "Publish/unpublish modules **depending on permissions**" — so it is not
    // the same permission as editing.
    ['course' => $course] = moduleMgmtCourse();
    $manager = actingPeopleAdmin(['courses.manage']);
    $module = makeMgmtModule($course->id, 'Unit 1', $manager->id);

    $this->actingAs($manager)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/modules/{$module->id}/status", ['status' => 'published'])
        ->assertForbidden();
});

it('shows status and description on the outline so they can be checked', function () {
    ['admin' => $admin, 'course' => $course] = moduleMgmtCourse();
    app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit 1', 'description' => 'Intro', 'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get("/catalog/courses/{$course->id}/outline")
        ->assertInertia(fn (Assert $page) => $page
            ->where('modules.0.status', 'draft')
            ->where('modules.0.description', 'Intro'));
});
