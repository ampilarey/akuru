<?php

use App\Domains\Courses\Actions\ResolveAssessmentSettingsAction;
use App\Domains\Courses\Actions\ScoreAssessmentSnapshotsAction;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use App\Domains\Progress\Actions\StartAssessmentAttemptAction;
use App\Domains\Progress\Actions\SubmitAssessmentAttemptAction;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SPEC §19 requires assessments to support "Auto marking, Teacher marking,
 * Mixed marking" and "Show/hide correct answers".
 *
 * Two of the columns §19's own table mandates were captured by the controller,
 * saved, and listed back — and then honoured nowhere. This is the same shape as
 * the §31 time-limit bug: the setting existed everywhere except the one place
 * that would act on it.
 *
 *   - `requires_teacher_marking` never reached `ResolveAssessmentSettingsAction`,
 *     so the scoring path could not have honoured it. An attempt waited for a
 *     human only when some *question* could not be auto-scored, which means a
 *     speaking or writing assessment built out of multiple-choice questions was
 *     scored and finalised with no teacher involved — and with
 *     `show_correct_answers` on, the student got the answer key with it.
 *     `MigrateLegacyAssessmentsAction` sets this true for every migrated legacy
 *     assignment, so there is real data carrying a flag that did nothing.
 *
 *   - `show_results` was resolved into settings and read by nothing, so a
 *     teacher who turned the mark off still showed it.
 */
uses(RefreshDatabase::class);

function markingAssessment(array $overrides = []): Assessment
{
    $course = Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Marking', 'slug' => 'marking-'.Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => 'Marking course',
        'slug' => 'marking-course-'.Str::random(6),
        'short_desc' => 'x', 'body' => 'y', 'cover_image' => '',
        'workflow_status' => 'published', 'course_type' => 'general', 'status' => 'open',
    ]);

    return Assessment::query()->create(array_merge([
        'course_id' => $course->id,
        'title' => 'Speaking paper',
        'status' => 'published',
        'max_score' => 10,
    ], $overrides));
}

/** One question that auto-scores cleanly — the case that used to finalise itself. */
function autoScorableSnapshots(): array
{
    return [[
        'question_id' => 1,
        'points' => 10,
        'pattern' => 'selection',
        'options' => [['id' => 'a'], ['id' => 'b']],
        'correct_answer' => ['a'],
    ]];
}

function markingAttempt(Assessment $assessment, int $studentId): AssessmentAttempt
{
    return AssessmentAttempt::query()->create([
        'assessment_id' => $assessment->id,
        'student_id' => $studentId,
        'attempt_number' => 1,
        'status' => 'in_progress',
        'answers' => [],
        'snapshots' => autoScorableSnapshots(),
        'max_score' => 10,
        'started_at' => now(),
    ])->fresh();
}

it('carries requires_teacher_marking through the settings the submit path reads', function () {
    $assessment = markingAssessment(['requires_teacher_marking' => true]);

    // The root cause: the flag never arrived here, exactly as
    // `time_limit_minutes` did not before §31.
    $settings = app(ResolveAssessmentSettingsAction::class)->execute((int) $assessment->id);

    expect($settings)->toHaveKey('requires_teacher_marking')
        ->and($settings['requires_teacher_marking'])->toBeTrue();
});

it('holds an auto-scorable attempt for a teacher when the assessment says so', function () {
    $result = app(ScoreAssessmentSnapshotsAction::class)->execute(
        autoScorableSnapshots(),
        ['1' => ['selected_ids' => ['a']]],
        null,
        requiresTeacherMarking: true,
    );

    // The marks are still computed — the teacher is not made to redo arithmetic
    // — but the attempt is not final, and it is not "passed" either.
    expect($result['status'])->toBe('submitted')
        ->and($result['score'])->toBe(10)
        ->and($result['passed'])->toBeFalse();
});

it('still auto-scores when the assessment does not require a teacher', function () {
    $result = app(ScoreAssessmentSnapshotsAction::class)->execute(
        autoScorableSnapshots(),
        ['1' => ['selected_ids' => ['a']]],
        null,
    );

    // The ordinary case must not become slower: most quizzes mark themselves.
    expect($result['status'])->toBe('scored')
        ->and($result['passed'])->toBeTrue();
});

it('sends a teacher-marked submission to the review queue rather than finalising it', function () {
    $assessment = markingAssessment(['requires_teacher_marking' => true]);
    $student = makeStudent(['first_name' => 'Marked', 'last_name' => 'Pupil']);
    $attempt = markingAttempt($assessment, $student->id);

    $result = app(SubmitAssessmentAttemptAction::class)->execute(
        (int) $assessment->id,
        null,
        ['1' => ['selected_ids' => ['a']]],
        (int) $student->id,
    );

    expect($result['result']['status'])->toBe('submitted')
        ->and($attempt->fresh()->status->value)->toBe('submitted');
});

it('hides the mark from the student when show_results is off', function () {
    $assessment = markingAssessment(['show_results' => false]);
    $student = makeStudent(['first_name' => 'Hidden', 'last_name' => 'Mark']);
    $attempt = markingAttempt($assessment, $student->id);
    $attempt->update(['status' => 'scored', 'score' => 7, 'item_scores' => [['question_id' => 1, 'score' => 7]]]);

    $payload = app(StartAssessmentAttemptAction::class)->serialize($attempt->fresh(), asStudent: true);

    expect($payload['score'])->toBeNull()
        ->and($payload['max_score'])->toBeNull()
        ->and($payload['item_scores'])->toBeNull()
        ->and($payload['show_results'])->toBeFalse();
});

it('keeps showing the mark to the teacher when show_results is off', function () {
    $assessment = markingAssessment(['show_results' => false]);
    $student = makeStudent(['first_name' => 'Teacher', 'last_name' => 'View']);
    $attempt = markingAttempt($assessment, $student->id);
    $attempt->update(['status' => 'scored', 'score' => 7]);

    // `show_results` hides the mark from the person who sat the paper, not from
    // the staff. `ListScoredAttemptsAction` serializes without `includeKeys` to
    // build a *teacher* report, so tying this to that flag would have blanked
    // the gradebook.
    $payload = app(StartAssessmentAttemptAction::class)->serialize($attempt->fresh());

    expect($payload['score'])->toBe(7);
});

it('still shows the mark to the student when show_results is on', function () {
    $assessment = markingAssessment(['show_results' => true]);
    $student = makeStudent(['first_name' => 'Shown', 'last_name' => 'Mark']);
    $attempt = markingAttempt($assessment, $student->id);
    $attempt->update(['status' => 'scored', 'score' => 9]);

    $payload = app(StartAssessmentAttemptAction::class)->serialize($attempt->fresh(), asStudent: true);

    expect($payload['score'])->toBe(9)
        ->and($payload['show_results'])->toBeTrue();
});

it('still gives the student their teacher feedback when the mark is hidden', function () {
    $assessment = markingAssessment(['show_results' => false]);
    $student = makeStudent(['first_name' => 'Feedback', 'last_name' => 'Pupil']);
    $attempt = markingAttempt($assessment, $student->id);
    $attempt->update(['status' => 'scored', 'score' => 3, 'feedback' => 'Work on your opening paragraph.']);

    $payload = app(StartAssessmentAttemptAction::class)->serialize($attempt->fresh(), asStudent: true);

    // `show_results` is about the mark. A teacher who wrote a comment meant it
    // to be read — withholding that too would make the setting useless.
    expect($payload['score'])->toBeNull()
        ->and($payload['feedback'])->toBe('Work on your opening paragraph.');
});
