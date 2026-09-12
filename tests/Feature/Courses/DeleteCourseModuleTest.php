<?php

use App\Domains\Courses\Actions\DeleteCourseModuleAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §12: "Course creators must be able to … **Delete draft modules if
 * safe**."
 *
 * There was no module delete anywhere — no route, no controller method, no
 * action. A module added by mistake was permanent, while the blocks inside it
 * could be removed freely.
 *
 * The foreign keys were already on the right side of this: `lessons`,
 * `content_blocks` and `student_lesson_progress` point at `course_modules` with
 * **ON DELETE RESTRICT**, the opposite of the course cascade §29 had to fix. The
 * database would refuse an unsafe delete by itself. What it would not do is
 * explain: a RESTRICT violation reaches the admin as a 500 and an SQL string.
 */
uses(RefreshDatabase::class);

function moduleCourse(): Course
{
    return Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Modules', 'slug' => 'modules-'.Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => 'Module course',
        'slug' => 'module-course-'.Str::random(6),
        'short_desc' => 'x', 'body' => 'y', 'cover_image' => '',
        'workflow_status' => 'published', 'course_type' => 'general', 'status' => 'open',
    ]);
}

function courseModule(Course $course, string $status = 'draft'): CourseModule
{
    return CourseModule::query()->create([
        'course_id' => $course->id,
        'title' => 'A module',
        'position' => 1,
        'status' => $status,
    ]);
}

it('deletes an empty draft module', function () {
    $module = courseModule(moduleCourse());

    app(DeleteCourseModuleAction::class)->execute($module);

    // Soft: the column and the trait both exist, so the row stays put while the
    // outline loses it.
    expect(CourseModule::query()->whereKey($module->id)->exists())->toBeFalse()
        ->and(CourseModule::withTrashed()->whereKey($module->id)->exists())->toBeTrue();
});

it('refuses a module that still holds lessons, and says how many', function () {
    $course = moduleCourse();
    $module = courseModule($course);
    Lesson::query()->create([
        'course_id' => $course->id,
        'course_module_id' => $module->id,
        'title' => 'A lesson',
        'slug' => 'a-lesson',
        'position' => 1,
    ]);

    try {
        app(DeleteCourseModuleAction::class)->execute($module);
        expect(false)->toBeTrue('the delete should have been refused');
    } catch (ValidationException $e) {
        // Without this the RESTRICT foreign key throws, and the admin gets a
        // 500 with an SQL string rather than a sentence.
        expect($e->errors()['module'][0])->toContain('1 lessons')
            ->and($e->errors()['module'][0])->toContain('§12');
    }

    expect(CourseModule::query()->whereKey($module->id)->exists())->toBeTrue();
});

it('refuses a published module even when it is empty', function () {
    $module = courseModule(moduleCourse(), 'published');

    // §12 says *draft* modules. A published one is part of a course people are
    // taking, empty or not.
    try {
        app(DeleteCourseModuleAction::class)->execute($module);
        expect(false)->toBeTrue('the delete should have been refused');
    } catch (ValidationException $e) {
        expect($e->errors()['module'][0])->toContain('draft');
    }

    expect(CourseModule::query()->whereKey($module->id)->exists())->toBeTrue();
});

it('ignores a lesson that was already deleted', function () {
    $course = moduleCourse();
    $module = courseModule($course);
    $lesson = Lesson::query()->create([
        'course_id' => $course->id,
        'course_module_id' => $module->id,
        'title' => 'Removed lesson',
        'slug' => 'removed-lesson',
        'position' => 1,
    ]);
    $lesson->delete();

    // A soft-deleted lesson is not in anybody's way, and counting it would
    // make the module undeletable forever with nothing visible holding it.
    app(DeleteCourseModuleAction::class)->execute($module);

    expect(CourseModule::query()->whereKey($module->id)->exists())->toBeFalse();
});

it('keeps module delete to people who can manage courses', function () {
    $course = moduleCourse();
    $module = courseModule($course);
    $plain = App\Domains\Identity\Models\User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($plain)
        ->delete(route('catalog.courses.modules.destroy', ['course' => $course->id, 'module' => $module->id]))
        ->assertForbidden();
});

it('refuses to delete a module through another course', function () {
    $module = courseModule(moduleCourse());
    $other = moduleCourse();
    $admin = App\Domains\Identity\Models\User::factory()->create();
    $admin->assignRole('super_admin');

    // The module id is a bare integer in the URL, so the course it belongs to
    // has to be checked rather than trusted.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->delete(route('catalog.courses.modules.destroy', ['course' => $other->id, 'module' => $module->id]))
        ->assertNotFound();
});
