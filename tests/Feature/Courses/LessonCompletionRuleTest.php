<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\EvaluateLessonCompletionAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\StartOrCompleteLessonProgressAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Enums\LessonCompletionMode;
use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §13 lists **"Completion rule"** among a lesson's fields and
 * **"Set completion rules"** among what a course creator must be able to do.
 * §27 opens "Admin must be able to configure completion rules", gives five for
 * a lesson, and closes:
 *
 *   > Use a dedicated completion calculation service.
 *   > Do not put completion rules directly inside controllers.
 *
 * There was no column, no service, and no rule.
 * `StartOrCompleteLessonProgressAction` took the status straight from the
 * controller, so `POST /learn/lessons/{id}/complete` recorded `completed`
 * unconditionally. The single clause of §27 that was implemented — "student
 * clicks complete" — was also the only one that could ever be true.
 *
 * **This is not cosmetic.** A student could open a lesson holding a required
 * activity, never attempt it, click Mark complete, and the lesson counted.
 * That feeds `CalculateCourseProgressAction`, which feeds the enrolment's
 * `progress_percentage`, which is exactly what §39's `min_progress_percent`
 * certificate rule is measured against — so a certificate could be issued on
 * a percentage earned by clicking past the work.
 *
 * Two of §27's five rules are implemented. The other three are **deliberately
 * absent rather than declared and ignored**, the same call §26 made for
 * unlock modes; `LessonCompletionMode` records what each would need.
 */
uses(RefreshDatabase::class);

function completionLesson(?string $rule = null): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Completion Lab '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Start',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Lesson one',
        'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Body'],
        'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Completion', 'last_name' => 'Student']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    if ($rule !== null) {
        app(SaveLessonAction::class)->execute([
            'course_module_id' => $module->id,
            'title' => $lesson->title,
            'slug' => $lesson->slug,
            'position' => $lesson->position,
            'completion_rule' => $rule,
        ], $lesson);
    }

    return compact('admin', 'course', 'module', 'lesson', 'user', 'enrollment');
}

it('stores the completion rule SPEC §13 names', function () {
    expect(Schema::hasColumn('lessons', 'completion_rule'))->toBeTrue();
});

it('keeps clicking complete as the default so nothing existing changes', function () {
    // Rule 9 in spirit: a lesson that says nothing keeps exactly the behaviour
    // it had.
    $lesson = new Lesson(['completion_rule' => null]);

    expect(app(EvaluateLessonCompletionAction::class)->mode($lesson))
        ->toBe(LessonCompletionMode::Click);
});

it('falls back to the default for a rule it does not implement', function () {
    // A lesson must never become uncompletable because its rule was written by
    // a newer version of the app than the one reading it.
    $lesson = new Lesson(['completion_rule' => ['mode' => 'teacher_approval']]);

    expect(app(EvaluateLessonCompletionAction::class)->mode($lesson))
        ->toBe(LessonCompletionMode::Click);
});

it('refuses to store a rule the engine does not enforce', function () {
    // §26's lesson: an option an admin can pick and the engine ignores asserts
    // a requirement on screen that is never checked.
    ['lesson' => $lesson] = completionLesson();

    expect(fn () => app(SaveLessonAction::class)->execute([
        'course_module_id' => $lesson->course_module_id,
        'title' => $lesson->title,
        'slug' => $lesson->slug,
        'completion_rule' => 'quiz_passed',
    ], $lesson))->toThrow(ValidationException::class);
});

it('lets a student click a lesson complete when that is the rule', function () {
    ['lesson' => $lesson, 'user' => $user] = completionLesson();
    app(PublishLessonAction::class)->execute($lesson->refresh());

    $result = app(StartOrCompleteLessonProgressAction::class)
        ->execute($lesson->id, $user, 'completed');

    expect($result['recorded'])->toBeTrue();
});

it('refuses completion while a required activity is unattempted', function () {
    // The defect, stated as a test. Before this slice the click was recorded
    // and the lesson counted toward course progress.
    ['lesson' => $lesson, 'user' => $user, 'course' => $course] = completionLesson('required_activities');
    Activity::query()->create([
        'course_id' => $course->id,
        'course_module_id' => $lesson->course_module_id,
        'lesson_id' => $lesson->id,
        'title' => 'Match the words',
        'pattern' => 'arrange',
        'activity_type' => 'matching',
        'data' => ['prompt' => 'Match them'],
        'is_required' => true,
    ]);
    app(PublishLessonAction::class)->execute($lesson->refresh());

    expect(fn () => app(StartOrCompleteLessonProgressAction::class)
        ->execute($lesson->id, $user, 'completed'))
        ->toThrow(ValidationException::class);
});

it('names what is still outstanding rather than just refusing', function () {
    ['lesson' => $lesson, 'course' => $course, 'enrollment' => $enrollment] = completionLesson('required_activities');
    Activity::query()->create([
        'course_id' => $course->id,
        'course_module_id' => $lesson->course_module_id,
        'lesson_id' => $lesson->id,
        'title' => 'Match the words',
        'pattern' => 'arrange',
        'activity_type' => 'matching',
        'data' => ['prompt' => 'Match them'],
        'is_required' => true,
    ]);

    $result = app(EvaluateLessonCompletionAction::class)->execute($lesson->refresh(), $enrollment->id);

    expect($result['allowed'])->toBeFalse()
        ->and($result['outstanding'])->toBe(['Match the words']);
});

it('allows completion once every required activity has been attempted', function () {
    ['lesson' => $lesson, 'user' => $user, 'course' => $course, 'enrollment' => $enrollment] = completionLesson('required_activities');
    $activity = Activity::query()->create([
        'course_id' => $course->id,
        'course_module_id' => $lesson->course_module_id,
        'lesson_id' => $lesson->id,
        'title' => 'Match the words',
        'pattern' => 'arrange',
        'activity_type' => 'matching',
        'data' => ['prompt' => 'Match them'],
        'is_required' => true,
    ]);
    ActivityAttempt::query()->create([
        'activity_id' => $activity->id,
        'course_id' => $course->id,
        'enrollment_id' => $enrollment->id,
        'student_id' => $enrollment->unified_student_id,
        'attempt_number' => 1,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);
    app(PublishLessonAction::class)->execute($lesson->refresh());

    expect(app(StartOrCompleteLessonProgressAction::class)
        ->execute($lesson->id, $user, 'completed')['recorded'])->toBeTrue();
});

it('does not require a pass, only an attempt', function () {
    // §27 lists "Required activities completed" and "Quiz passed" as two
    // separate rules, so a submitted-but-wrong attempt satisfies this one.
    // Passing is the rule that is not built.
    ['lesson' => $lesson, 'course' => $course, 'enrollment' => $enrollment] = completionLesson('required_activities');
    $activity = Activity::query()->create([
        'course_id' => $course->id,
        'course_module_id' => $lesson->course_module_id,
        'lesson_id' => $lesson->id,
        'title' => 'Hard quiz',
        'pattern' => 'selection',
        'activity_type' => 'mcq',
        'data' => ['prompt' => 'Pick one'],
        'max_score' => 10,
        'passing_score' => 8,
        'is_required' => true,
    ]);
    ActivityAttempt::query()->create([
        'activity_id' => $activity->id,
        'course_id' => $course->id,
        'enrollment_id' => $enrollment->id,
        'student_id' => $enrollment->unified_student_id,
        'attempt_number' => 1,
        'status' => 'scored',
        'score' => 0,
        'submitted_at' => now(),
    ]);

    expect(app(EvaluateLessonCompletionAction::class)->execute($lesson->refresh(), $enrollment->id)['allowed'])
        ->toBeTrue();
});

it('ignores optional activities', function () {
    ['lesson' => $lesson, 'course' => $course, 'enrollment' => $enrollment] = completionLesson('required_activities');
    Activity::query()->create([
        'course_id' => $course->id,
        'course_module_id' => $lesson->course_module_id,
        'lesson_id' => $lesson->id,
        'title' => 'Extra practice',
        'pattern' => 'selection',
        'activity_type' => 'mcq',
        'data' => ['prompt' => 'Optional'],
        'is_required' => false,
    ]);

    expect(app(EvaluateLessonCompletionAction::class)->execute($lesson->refresh(), $enrollment->id)['allowed'])
        ->toBeTrue();
});

it('treats a rule with nothing to require as satisfied, not impossible', function () {
    // Refusing here would strand every lesson whose activities were later
    // removed.
    ['lesson' => $lesson, 'enrollment' => $enrollment] = completionLesson('required_activities');

    expect(app(EvaluateLessonCompletionAction::class)->execute($lesson->refresh(), $enrollment->id)['allowed'])
        ->toBeTrue();
});

it('still records opening a gated lesson as in progress', function () {
    // Only `completed` is gated. If opening were gated too, a student could
    // never reach the activity the rule demands.
    ['lesson' => $lesson, 'user' => $user, 'course' => $course] = completionLesson('required_activities');
    Activity::query()->create([
        'course_id' => $course->id,
        'course_module_id' => $lesson->course_module_id,
        'lesson_id' => $lesson->id,
        'title' => 'Match the words',
        'pattern' => 'arrange',
        'activity_type' => 'matching',
        'data' => ['prompt' => 'Match them'],
        'is_required' => true,
    ]);
    app(PublishLessonAction::class)->execute($lesson->refresh());

    expect(app(StartOrCompleteLessonProgressAction::class)
        ->execute($lesson->id, $user, 'in_progress')['recorded'])->toBeTrue();
});

it('sets the rule through the outline screen', function () {
    ['lesson' => $lesson, 'course' => $course, 'admin' => $admin] = completionLesson();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/lessons/{$lesson->id}/completion-rule", [
            'completion_rule' => 'required_activities',
        ])
        ->assertRedirect();

    expect($lesson->refresh()->completion_rule)->toBe(['mode' => 'required_activities']);
});

it('shows the rule on the outline so it can be checked', function () {
    ['lesson' => $lesson, 'course' => $course, 'admin' => $admin] = completionLesson('required_activities');

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get("/catalog/courses/{$course->id}/outline")
        ->assertInertia(fn ($page) => $page
            ->where('modules.0.lessons.0.completion_rule', 'required_activities'));
});
