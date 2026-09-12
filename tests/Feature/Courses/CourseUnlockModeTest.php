<?php

use App\Domains\Courses\Actions\ResolveCourseUnlockModeAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Enums\UnlockMode;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Progress\Actions\EvaluateLessonUnlockAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §26 "Unlock Rules":
 *
 *   > Admin must be able to configure unlock rules.
 *   >
 *   > Supported unlock rules: All lessons open · Complete previous lesson
 *   > first · Complete previous module first · Pass quiz first · …
 *   >
 *   > Unlock rules should be stored in JSON settings at course, module,
 *   > lesson, or offering level.
 *
 * Nothing was configurable. `EvaluateLessonUnlockAction` implemented exactly
 * one of the eleven rules and applied it to every course unconditionally, so
 * "All lessons open" — the **first** rule §26 lists — could not be chosen.
 * A reference course or a set of independent pages was unbuildable: a student
 * had to walk the whole sequence to reach the last page.
 */
uses(RefreshDatabase::class);

function unlockCourse(?array $rules): Course
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Unlock course '.uniqid(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $course->update(['unlock_rules' => $rules]);

    return $course->refresh();
}

it('defaults to sequential, so no existing course changes on deploy', function () {
    // Rule 9 in spirit: the column is new and NULL everywhere, and NULL must
    // mean exactly what the hardcoded behaviour meant.
    expect(app(ResolveCourseUnlockModeAction::class)->execute(unlockCourse(null)))
        ->toBe(UnlockMode::Sequential);
});

it('reads an explicit all-open setting', function () {
    expect(app(ResolveCourseUnlockModeAction::class)->execute(unlockCourse(['mode' => 'all_open'])))
        ->toBe(UnlockMode::AllOpen);
});

it('falls back to sequential for an unknown mode rather than throwing', function () {
    // A malformed setting must not lock every student out of a course, and
    // sequential is the stricter of the two — failing closed on access, open
    // on error.
    expect(app(ResolveCourseUnlockModeAction::class)->execute(unlockCourse(['mode' => 'telepathy'])))
        ->toBe(UnlockMode::Sequential)
        ->and(app(ResolveCourseUnlockModeAction::class)->execute(unlockCourse(['mode' => 42])))
        ->toBe(UnlockMode::Sequential);
});

it('falls back to sequential when there is no course at all', function () {
    expect(app(ResolveCourseUnlockModeAction::class)->execute(null))->toBe(UnlockMode::Sequential);
});

it('keeps the third lesson locked under sequential', function () {
    // The behaviour every course had, and must keep.
    $unlocked = app(EvaluateLessonUnlockAction::class)->execute(
        30, [10, 20, 30], [10], false, false,
    );

    expect($unlocked)->toBeFalse();
});

it('opens the third lesson under all-open', function () {
    // §26's first listed rule, previously unreachable.
    $unlocked = app(EvaluateLessonUnlockAction::class)->execute(
        30, [10, 20, 30], [10], false, true,
    );

    expect($unlocked)->toBeTrue();
});

it('opens a lesson that is not in the required list at all under all-open', function () {
    // Sequential returns false for an unknown lesson by walking off the end of
    // the list. All-open must not inherit that.
    expect(app(EvaluateLessonUnlockAction::class)->execute(99, [10, 20], [], false, true))->toBeTrue()
        ->and(app(EvaluateLessonUnlockAction::class)->execute(99, [10, 20], [], false, false))->toBeFalse();
});

it('leaves preview lessons open under either mode', function () {
    expect(app(EvaluateLessonUnlockAction::class)->execute(30, [10, 20, 30], [], true, false))->toBeTrue()
        ->and(app(EvaluateLessonUnlockAction::class)->execute(30, [10, 20, 30], [], true, true))->toBeTrue();
});

it('saves the mode through the action', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Reference library',
        'subject_id' => CourseSubject::query()->value('id'),
        'unlock_mode' => 'all_open',
        'created_by' => $admin->id,
    ]);

    expect($course->unlock_rules)->toBe(['mode' => 'all_open']);
});

it('leaves the setting alone on an update that does not mention it', function () {
    // A course saved as all-open must not be silently reset to sequential by
    // an edit that only changes the title.
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Reference library',
        'subject_id' => CourseSubject::query()->value('id'),
        'unlock_mode' => 'all_open',
        'created_by' => $admin->id,
    ]);

    app(SaveEngineCourseAction::class)->execute(['title' => 'Renamed library'], $course);

    expect($course->refresh()->unlock_rules)->toBe(['mode' => 'all_open']);
});

it('refuses an unknown mode on save', function () {
    $admin = actingPeopleAdmin(['courses.manage']);

    expect(fn () => app(SaveEngineCourseAction::class)->execute([
        'title' => 'Bad mode',
        'subject_id' => CourseSubject::query()->value('id'),
        'unlock_mode' => 'whenever',
        'created_by' => $admin->id,
    ]))->toThrow(ValidationException::class);
});

it('offers the modes and each course current mode to the catalog screen', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    app(SaveEngineCourseAction::class)->execute([
        'title' => 'Shown course',
        'subject_id' => CourseSubject::query()->value('id'),
        'unlock_mode' => 'all_open',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/courses')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('unlockModes', 2)
            ->where('rows.0.unlock_mode', 'all_open'));
});

it('honours the course mode through the real access check, not just the evaluator', function () {
    // The evaluator tests above pass a flag directly. This one goes through
    // AuthorizeLessonAccessAction, which is where the course setting is
    // actually read — the wiring, not the rule.
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Wired course',
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(\App\Domains\Courses\Actions\SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit', 'created_by' => $admin->id,
    ]);

    $lessons = [];
    foreach (['First', 'Second'] as $title) {
        $lesson = app(\App\Domains\Courses\Actions\SaveLessonAction::class)->execute([
            'course_module_id' => $module->id, 'title' => $title, 'created_by' => $admin->id,
        ]);
        app(\App\Domains\Courses\Actions\SaveContentBlockAction::class)->execute([
            'lesson_id' => $lesson->id, 'type' => 'text',
            'data' => ['body' => $title], 'settings' => ['direction' => 'auto'],
        ]);
        app(\App\Domains\Courses\Actions\PublishLessonAction::class)->execute($lesson, $admin->id);
        $lessons[] = $lesson->refresh();
    }

    $student = makeStudent(['first_name' => 'Unlock', 'last_name' => 'Student']);
    $enrollment = \App\Domains\Courses\Models\CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => makeRegistrationStudent()->id,
        'unified_student_id' => $student->id,
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);

    $auth = app(\App\Domains\Courses\Actions\AuthorizeLessonAccessAction::class);
    $second = $lessons[1];

    // Nothing completed: sequential keeps the second lesson shut.
    expect($auth->isUnlocked($second, $enrollment->id))->toBeFalse();

    $course->update(['unlock_rules' => ['mode' => 'all_open']]);

    expect($auth->isUnlocked($second->refresh(), $enrollment->id))->toBeTrue();
});

it('names only the rules it actually implements', function () {
    // §26 lists eleven. Two need no data the evaluator lacks; the other nine
    // each need a quiz result, an approval, a payment or a date. An enum case
    // that silently behaved like sequential would be worse than its absence,
    // because a course could claim to require teacher approval and not.
    expect(array_map(fn (UnlockMode $m) => $m->value, UnlockMode::cases()))
        ->toBe(['all_open', 'sequential']);
});
