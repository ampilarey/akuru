<?php

use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Enums\AssessmentType;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §19 "Assessment System".
 *
 * Most of §19 is **cleared, not faulted**, and that is worth saying first: the
 * `assessments` table carries every field the section names; retake limits are
 * enforced at attempt start; time limits resolve to a real deadline and a late
 * submit keeps only what was saved before it expired; `randomize_questions` is
 * applied when snapshots are built; and an in-progress attempt is returned
 * rather than duplicated, which is §19's "Resume incomplete attempt".
 *
 * What was wrong is the way an assessment is *created*.
 *
 * `CatalogAssessmentController::payload()` built its array entirely out of
 * `$request->input()` / `boolean()` / `filled()` — **no `validate()` call
 * anywhere on the save path**. CLAUDE.md rule 5 asks for "authorize →
 * validate into DTO → call Action"; this authorized and then trusted.
 *
 * And §19's eleven assessment types lived as a **hardcoded array inside that
 * controller**, with `SaveAssessmentAction` storing
 * `(string) ($data['assessment_type'] ?? 'lesson_quiz')`. The list on screen
 * was advisory: any string at all was storable, and `assessment_type` is what
 * §19's reporting and the §34–§37 dashboards group by.
 */
uses(RefreshDatabase::class);

function assessmentCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Assessments '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'course');
}

it('carries every assessment type §19 names', function () {
    $values = array_map(fn (AssessmentType $t): string => $t->value, AssessmentType::cases());

    foreach ([
        'lesson_quiz', 'module_test', 'placement_test', 'final_exam',
        'listening', 'speaking', 'reading', 'writing', 'practical', 'mixed', 'assignment',
    ] as $type) {
        expect($values)->toContain($type);
    }
});

it('refuses an assessment type §19 does not define', function () {
    // The defect: the eleven types were a hardcoded controller array and
    // nothing validated against it, so any string was storable.
    ['course' => $course] = assessmentCourse();

    expect(fn () => app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Bogus',
        'assessment_type' => 'interpretive_dance',
    ]))->toThrow(ValidationException::class);
});

it('validates the form instead of trusting it (rule 5)', function () {
    // `payload()` had no validate() call at all, so every one of these reached
    // the Action and whatever it did not reject was stored.
    ['admin' => $admin, 'course' => $course] = assessmentCourse();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/assessments", [
            'title' => '',
            'assessment_type' => 'not_a_type',
            'status' => 'not_a_status',
            'time_limit_minutes' => -5,
            'retake_limit' => -1,
        ])
        ->assertSessionHasErrors(['title', 'assessment_type', 'status', 'time_limit_minutes', 'retake_limit']);
});

it('refuses a time limit that is over before it starts', function () {
    // Zero is not "no limit" — null is. A zero-minute assessment expires on
    // the first tick, and `ResolveAssessmentDeadlineAction` would honour it.
    ['admin' => $admin, 'course' => $course] = assessmentCourse();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/assessments", [
            'title' => 'Instant',
            'time_limit_minutes' => 0,
        ])
        ->assertSessionHasErrors('time_limit_minutes');
});

it('refuses a module from a different course', function () {
    // §19 hangs Module ID off the assessment and every reader assumes it is
    // inside `course_id`. Nothing checked it.
    ['admin' => $admin, 'course' => $course] = assessmentCourse();
    ['course' => $other] = assessmentCourse();
    $foreign = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $other->id, 'title' => 'Elsewhere', 'created_by' => $admin->id,
    ]);

    expect(fn () => app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Misattached',
        'course_module_id' => $foreign->id,
    ]))->toThrow(ValidationException::class);
});

it('refuses a lesson from a different course', function () {
    ['admin' => $admin, 'course' => $course] = assessmentCourse();
    ['course' => $other] = assessmentCourse();
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $other->id, 'title' => 'Elsewhere', 'created_by' => $admin->id,
    ]);
    $foreign = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Far lesson', 'created_by' => $admin->id,
    ]);

    expect(fn () => app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Misattached',
        'lesson_id' => $foreign->id,
    ]))->toThrow(ValidationException::class);
});

it('accepts a module that does belong to the course', function () {
    ['admin' => $admin, 'course' => $course] = assessmentCourse();
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit 1', 'created_by' => $admin->id,
    ]);

    $assessment = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Module test',
        'assessment_type' => 'module_test',
        'course_module_id' => $module->id,
    ]);

    expect((int) $assessment->course_module_id)->toBe((int) $module->id)
        ->and($assessment->assessment_type)->toBe(AssessmentType::ModuleTest);
});

it('keeps the type an assessment already has when an edit does not mention it', function () {
    ['course' => $course] = assessmentCourse();
    $assessment = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Final',
        'assessment_type' => 'final_exam',
    ]);

    $updated = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Final renamed',
    ], $assessment);

    expect($updated->assessment_type)->toBe(AssessmentType::FinalExam);
});

it('still allows a passing score above max score, which the readers rely on', function () {
    // Deliberately NOT validated. Legacy rows express a passing score as a
    // percentage on a small-max quiz, and `TeacherReviewReportTest` pins a
    // reader that treats `passing_score > max_score` as a percent on purpose.
    // Forbidding it here would break that reading, and choosing between the
    // two meanings is a decision, not a cleanup.
    ['course' => $course] = assessmentCourse();

    $assessment = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Percent quiz',
        'passing_score' => 50,
        'max_score' => 2,
    ]);

    expect((int) $assessment->passing_score)->toBe(50)
        ->and((int) $assessment->max_score)->toBe(2);
});

it('serves the type list from the enum, not a hardcoded controller array', function () {
    ['admin' => $admin, 'course' => $course] = assessmentCourse();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get("/catalog/courses/{$course->id}/assessments")
        ->assertInertia(fn (Assert $page) => $page
            ->has('types', 11)
            ->where('types.0.value', 'lesson_quiz')
            ->where('types.0.label', 'Lesson quiz'));
});

it('stores the type as the enum it now is', function () {
    ['admin' => $admin, 'course' => $course] = assessmentCourse();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/assessments", [
            'title' => 'Speaking check',
            'assessment_type' => 'speaking',
        ])
        ->assertRedirect();

    expect(Assessment::query()->latest('id')->first()->assessment_type)
        ->toBe(AssessmentType::Speaking);
});
