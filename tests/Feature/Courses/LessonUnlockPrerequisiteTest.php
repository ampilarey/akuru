<?php

use App\Domains\Courses\Actions\AuthorizeLessonAccessAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\EvaluateLessonPrerequisiteAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Enums\UnlockMode;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §26 "Unlock Rules" lists eleven and says where they live:
 *
 *   > Unlock rules should be stored in JSON settings at **course, module,
 *   > lesson, or offering level**.
 *   >
 *   > Create an `UnlockRuleEvaluator` service. Do not scatter unlock logic
 *   > across controllers or React components.
 *
 * Only the **course** level existed, carrying two of the eleven rules. This
 * adds the **lesson** level and §26's third rule, "Pass quiz first".
 *
 * Three sections were pointing at this one missing column. §13 lists
 * **"Unlock rule"** among a lesson's own fields. §26 asks for lesson-level
 * storage outright. And §19's `settings.lock_next_lesson` is the same idea
 * written badly — a bare boolean, written by two Actions, read by nothing, and
 * unable to say *which* quiz. Naming the assessment is what makes it
 * enforceable, and is why no control was ever added for that boolean.
 *
 * The evaluator stays the single place that decides, and stays ignorant of
 * assessments: `$prerequisiteMet` arrives as a plain fact, exactly as
 * `$allOpen` does, because `LessonUnlockEvaluator` lives in Progress and rule
 * 3 forbids it knowing how Courses spells a quiz.
 */
uses(RefreshDatabase::class);

function prereqFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Gated '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);
    // Course-level "all open", so the only thing that can lock the second
    // lesson is its own rule — which is the case §26's lesson level exists for.
    $course->update(['unlock_rules' => ['mode' => 'all_open']]);

    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit', 'created_by' => $admin->id,
    ]);

    $lessons = [];
    foreach (['First', 'Gated'] as $title) {
        $lesson = app(SaveLessonAction::class)->execute([
            'course_module_id' => $module->id, 'title' => $title, 'created_by' => $admin->id,
        ]);
        app(SaveContentBlockAction::class)->execute([
            'lesson_id' => $lesson->id, 'type' => 'text', 'data' => ['body' => 'Body'], 'created_by' => $admin->id,
        ]);
        app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
        $lessons[] = $lesson->fresh();
    }

    $quiz = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Unit 1 quiz',
        'assessment_type' => 'lesson_quiz',
        'status' => 'published',
        'passing_score' => 6,
        'max_score' => 10,
        'created_by' => $admin->id,
    ]);

    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Gated', 'last_name' => 'Learner']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    return compact('admin', 'course', 'module', 'lessons', 'quiz', 'user', 'student', 'enrollment');
}

function gateOn(int $lessonId, int $assessmentId, int $moduleId, string $title): void
{
    app(SaveLessonAction::class)->execute([
        'course_module_id' => $moduleId,
        'title' => $title,
        'unlock_rule' => ['mode' => 'pass_assessment', 'assessment_id' => $assessmentId],
    ], Lesson::query()->findOrFail($lessonId));
}

function attemptQuiz(int $assessmentId, array $ctx, float $score, string $status = 'scored'): AssessmentAttempt
{
    return AssessmentAttempt::query()->create([
        'assessment_id' => $assessmentId,
        'course_id' => $ctx['course']->id,
        'enrollment_id' => $ctx['enrollment']->id,
        'student_id' => $ctx['enrollment']->unified_student_id,
        'attempt_number' => 1,
        'status' => $status,
        'score' => $score,
        'max_score' => 10,
        'submitted_at' => now(),
    ]);
}

it('stores an unlock rule at the lesson level, as §26 and §13 require', function () {
    expect(Schema::hasColumn('lessons', 'unlock_rule'))->toBeTrue();
});

it('locks a lesson until its named quiz is passed', function () {
    // §26's "Pass quiz first", which had no implementation and no storage.
    $ctx = prereqFixture();
    gateOn($ctx['lessons'][1]->id, $ctx['quiz']->id, $ctx['module']->id, 'Gated');

    $gated = $ctx['lessons'][1]->refresh();

    expect(app(AuthorizeLessonAccessAction::class)->isUnlocked($gated, $ctx['enrollment']->id))
        ->toBeFalse();
});

it('opens it once the quiz is passed', function () {
    $ctx = prereqFixture();
    gateOn($ctx['lessons'][1]->id, $ctx['quiz']->id, $ctx['module']->id, 'Gated');
    attemptQuiz($ctx['quiz']->id, $ctx, 8.0);

    expect(app(AuthorizeLessonAccessAction::class)->isUnlocked($ctx['lessons'][1]->refresh(), $ctx['enrollment']->id))
        ->toBeTrue();
});

it('keeps it locked on a failing mark', function () {
    $ctx = prereqFixture();
    gateOn($ctx['lessons'][1]->id, $ctx['quiz']->id, $ctx['module']->id, 'Gated');
    attemptQuiz($ctx['quiz']->id, $ctx, 3.0);

    expect(app(AuthorizeLessonAccessAction::class)->isUnlocked($ctx['lessons'][1]->refresh(), $ctx['enrollment']->id))
        ->toBeFalse();
});

it('does not unlock on a mark no teacher has agreed to yet', function () {
    // §27's lesson, applied here: a submitted attempt carries a provisional
    // auto-score. Unlocking on it would open a lesson that a later marking
    // could close again.
    $ctx = prereqFixture();
    gateOn($ctx['lessons'][1]->id, $ctx['quiz']->id, $ctx['module']->id, 'Gated');
    attemptQuiz($ctx['quiz']->id, $ctx, 9.0, 'submitted');

    expect(app(AuthorizeLessonAccessAction::class)->isUnlocked($ctx['lessons'][1]->refresh(), $ctx['enrollment']->id))
        ->toBeFalse();
});

it('gates even in an all-open course, which is the point of lesson level', function () {
    // The fixture's course is `all_open`. A reference course with one gated
    // chapter is exactly why §26 stores rules per lesson as well as per course.
    $ctx = prereqFixture();
    gateOn($ctx['lessons'][1]->id, $ctx['quiz']->id, $ctx['module']->id, 'Gated');

    $auth = app(AuthorizeLessonAccessAction::class);

    expect($auth->isUnlocked($ctx['lessons'][0]->refresh(), $ctx['enrollment']->id))->toBeTrue()
        ->and($auth->isUnlocked($ctx['lessons'][1]->refresh(), $ctx['enrollment']->id))->toBeFalse();
});

it('lets the rule lapse rather than bite when the quiz is deleted', function () {
    // Locking every student out of a lesson because an author deleted its
    // prerequisite punishes them for someone else's edit.
    $ctx = prereqFixture();
    gateOn($ctx['lessons'][1]->id, $ctx['quiz']->id, $ctx['module']->id, 'Gated');
    $ctx['quiz']->delete();

    expect(app(EvaluateLessonPrerequisiteAction::class)
        ->execute($ctx['lessons'][1]->refresh(), $ctx['enrollment']->unified_student_id)['met'])
        ->toBeTrue();
});

it('reads the passing bar the way the rest of the codebase does', function () {
    // `TeacherReviewReportTest` pins a reader that treats `passing_score >
    // max_score` as a percent, for legacy rows expressing a percent on a
    // small-max quiz. A lesson must not unlock on a different definition of
    // "passed" than the report shows.
    $ctx = prereqFixture();
    $ctx['quiz']->update(['passing_score' => 70, 'max_score' => 10]);
    gateOn($ctx['lessons'][1]->id, $ctx['quiz']->id, $ctx['module']->id, 'Gated');

    attemptQuiz($ctx['quiz']->id, $ctx, 6.0);
    expect(app(AuthorizeLessonAccessAction::class)->isUnlocked($ctx['lessons'][1]->refresh(), $ctx['enrollment']->id))
        ->toBeFalse();

    AssessmentAttempt::query()->update(['score' => 8.0]);
    expect(app(AuthorizeLessonAccessAction::class)->isUnlocked($ctx['lessons'][1]->refresh(), $ctx['enrollment']->id))
        ->toBeTrue();
});

it('refuses a lesson-level rule the evaluator does not enforce', function () {
    // §26's principle throughout: an option an admin can pick and the engine
    // ignores asserts a requirement that is never checked.
    $ctx = prereqFixture();

    expect(fn () => app(SaveLessonAction::class)->execute([
        'course_module_id' => $ctx['module']->id,
        'title' => 'Gated',
        'unlock_rule' => ['mode' => 'teacher_approval'],
    ], $ctx['lessons'][1]))->toThrow(ValidationException::class);
});

it('refuses a pass-quiz rule that names no quiz', function () {
    $ctx = prereqFixture();

    expect(fn () => app(SaveLessonAction::class)->execute([
        'course_module_id' => $ctx['module']->id,
        'title' => 'Gated',
        'unlock_rule' => ['mode' => 'pass_assessment'],
    ], $ctx['lessons'][1]))->toThrow(ValidationException::class);
});

it('sets and clears the rule through the outline screen', function () {
    $ctx = prereqFixture();
    $lesson = $ctx['lessons'][1];

    $this->actingAs($ctx['admin'])
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$ctx['course']->id}/lessons/{$lesson->id}/unlock-rule", [
            'mode' => 'pass_assessment',
            'assessment_id' => $ctx['quiz']->id,
        ])
        ->assertRedirect();

    expect($lesson->refresh()->unlock_rule)
        ->toBe(['mode' => 'pass_assessment', 'assessment_id' => $ctx['quiz']->id]);

    $this->actingAs($ctx['admin'])
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$ctx['course']->id}/lessons/{$lesson->id}/unlock-rule", ['mode' => ''])
        ->assertRedirect();

    expect($lesson->refresh()->unlock_rule)->toBeNull();
});

it('keeps the evaluator ignorant of assessments (rule 3)', function () {
    // §26 wants one evaluator, and rule 3 wants it not to know what a quiz is.
    // The prerequisite arrives as a plain bool, like `$allOpen` before it.
    $progress = (string) file_get_contents(base_path('app/Domains/Progress/Actions/EvaluateLessonUnlockAction.php'));

    expect($progress)->not->toContain('Assessment')
        ->and($progress)->toContain('$prerequisiteMet')
        ->and(app(EvaluateLessonPrerequisiteAction::class))
        ->toBeInstanceOf(EvaluateLessonPrerequisiteAction::class);
});

it('counts three of §26 eleven rules, and says so', function () {
    expect(array_map(fn (UnlockMode $m) => $m->value, UnlockMode::cases()))
        ->toBe(['all_open', 'sequential', 'pass_assessment']);
});
