<?php

use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use App\Domains\Progress\Actions\ResolveAssessmentDeadlineAction;
use App\Domains\Progress\Actions\SaveAssessmentAttemptAction;
use App\Domains\Progress\Actions\StartAssessmentAttemptAction;
use App\Domains\Progress\Actions\SubmitAssessmentAttemptAction;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §31: "Assessment countdowns and time limits must be computed
 * server-side from `attempt.started_at` and configured time limit. Never trust
 * client device clocks for time-limit enforcement."
 *
 * `time_limit_minutes` was settable in the admin screen, stored, and listed to
 * the student — and enforced nowhere. `ResolveAssessmentSettingsAction` did not
 * even return it, so the submit path could not have honoured it if it had
 * wanted to, and there was no client-side countdown either. A teacher set
 * thirty minutes and a student could take a week.
 *
 * The design constraint these tests pin: enforce the limit **without throwing
 * away work**. Autosave has been writing answers throughout, so an expired
 * attempt is scored on what was in hand when time ran out. Refusing outright
 * would punish a slow connection exactly as hard as cheating.
 */
uses(RefreshDatabase::class);

function timedAssessment(?int $minutes): Assessment
{
    $course = Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Timed', 'slug' => 'timed-'.Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => 'Timed course',
        'slug' => 'timed-course-'.Str::random(6),
        'short_desc' => 'x', 'body' => 'y', 'cover_image' => '',
        'workflow_status' => 'published', 'course_type' => 'general', 'status' => 'open',
    ]);

    return Assessment::query()->create([
        'course_id' => $course->id,
        'title' => 'Timed quiz',
        'status' => 'published',
        'max_score' => 10,
        'time_limit_minutes' => $minutes,
    ]);
}

function startedAttempt(Assessment $assessment, int $minutesAgo, ?int $studentId = null): AssessmentAttempt
{
    // `assessment_attempts.student_id` is NOT NULL — an attempt is always
    // somebody's, which is the whole reason a time limit has to be fair.
    $studentId ??= makeStudent(['first_name' => 'Timed', 'last_name' => 'Pupil'])->id;

    $attempt = AssessmentAttempt::query()->create([
        'assessment_id' => $assessment->id,
        'student_id' => $studentId,
        'attempt_number' => 1,
        'status' => 'in_progress',
        'answers' => ['q1' => 'saved while the clock ran'],
        'snapshots' => [],
        'max_score' => 10,
        'started_at' => now()->subMinutes($minutesAgo),
        'last_saved_at' => now()->subMinutes($minutesAgo),
    ]);

    return $attempt->fresh();
}

it('reports no deadline when the assessment is untimed', function () {
    $assessment = timedAssessment(null);
    $attempt = startedAttempt($assessment, 500);

    $deadline = app(ResolveAssessmentDeadlineAction::class)->execute($attempt, ['time_limit_minutes' => null]);

    // Most assessments are untimed; the limit must stay opt-in.
    expect($deadline['limited'])->toBeFalse()
        ->and($deadline['expired'])->toBeFalse()
        ->and($deadline['seconds_remaining'])->toBeNull();
});

it('counts down from started_at, not from now', function () {
    $assessment = timedAssessment(30);
    $attempt = startedAttempt($assessment, 10);

    $deadline = app(ResolveAssessmentDeadlineAction::class)->execute($attempt, ['time_limit_minutes' => 30]);

    expect($deadline['limited'])->toBeTrue()
        ->and($deadline['expired'])->toBeFalse()
        // 20 minutes left, give or take the second this test takes to run.
        ->and($deadline['seconds_remaining'])->toBeGreaterThan(19 * 60)
        ->and($deadline['seconds_remaining'])->toBeLessThanOrEqual(20 * 60);
});

it('never reports a negative countdown', function () {
    $assessment = timedAssessment(5);
    $attempt = startedAttempt($assessment, 60);

    $deadline = app(ResolveAssessmentDeadlineAction::class)->execute($attempt, ['time_limit_minutes' => 5]);

    // A countdown below zero reads as a bug to whoever is watching it.
    expect($deadline['seconds_remaining'])->toBe(0)
        ->and($deadline['expired'])->toBeTrue()
        ->and($deadline['seconds_over'])->toBeGreaterThan(50 * 60);
});

it('accepts a submission inside the grace window', function () {
    $assessment = timedAssessment(10);
    // Ten minutes and a few seconds ago: over the line, inside the grace.
    $attempt = AssessmentAttempt::query()->create([
        'assessment_id' => $assessment->id,
        'student_id' => makeStudent(['first_name' => 'Grace', 'last_name' => 'Pupil'])->id,
        'attempt_number' => 1,
        'status' => 'in_progress',
        'answers' => [],
        'snapshots' => [],
        'max_score' => 10,
        'started_at' => now()->subMinutes(10)->subSeconds(5),
    ]);

    $deadline = app(ResolveAssessmentDeadlineAction::class)
        ->execute($attempt->fresh(), ['time_limit_minutes' => 10]);

    // A student pressing Submit on the last second still has to get the
    // request across the network. Refusing that fails the person who obeyed
    // the limit.
    expect($deadline['expired'])->toBeFalse();
});

it('keeps the autosaved answers when a submission arrives too late', function () {
    $assessment = timedAssessment(10);
    $attempt = startedAttempt($assessment, 60);

    $result = app(SubmitAssessmentAttemptAction::class)->execute(
        $assessment->id,
        null,
        ['q1' => 'typed an hour after time ran out'],
        (int) $attempt->student_id,
    );

    // The late answers are discarded; the ones saved while the clock ran are
    // what get scored. No work that was saved in time is lost.
    expect($result['expired'])->toBeTrue()
        ->and($result['seconds_over'])->toBeGreaterThan(45 * 60)
        ->and($attempt->fresh()->answers)->toBe(['q1' => 'saved while the clock ran']);
});

it('takes the submitted answers when they arrive in time', function () {
    $assessment = timedAssessment(60);
    $attempt = startedAttempt($assessment, 5);

    $result = app(SubmitAssessmentAttemptAction::class)->execute(
        $assessment->id,
        null,
        ['q1' => 'answered with time to spare'],
        (int) $attempt->student_id,
    );

    expect($result['expired'])->toBeFalse()
        ->and($attempt->fresh()->answers)->toBe(['q1' => 'answered with time to spare']);
});

it('refuses to autosave after the deadline', function () {
    $assessment = timedAssessment(10);
    $attempt = startedAttempt($assessment, 60);

    // The loophole that would make the submit-side cut-off pointless: a browser
    // left open past the deadline keeps posting, and every post would become
    // "what was in hand when time ran out".
    expect(fn () => app(SaveAssessmentAttemptAction::class)->execute(
        $assessment->id,
        null,
        ['q1' => 'still typing, an hour later'],
        (int) $attempt->student_id,
    ))->toThrow(ValidationException::class);
});

it('still autosaves while time remains', function () {
    $assessment = timedAssessment(60);
    $attempt = startedAttempt($assessment, 5);

    app(SaveAssessmentAttemptAction::class)->execute(
        $assessment->id,
        null,
        ['q1' => 'work in progress'],
        (int) $attempt->student_id,
    );

    expect($attempt->fresh()->answers)->toBe(['q1' => 'work in progress']);
});

it('serves the countdown to the client rather than letting it compute one', function () {
    $assessment = timedAssessment(45);
    $attempt = startedAttempt($assessment, 5);

    $payload = app(StartAssessmentAttemptAction::class)->serialize($attempt);

    // §31's second sentence: a client computing remaining time from its own
    // clock disagrees with the server the moment that clock is wrong, and the
    // student finds out only when their submission is refused.
    expect($payload)->toHaveKeys(['time_limit_minutes', 'deadline_at', 'seconds_remaining', 'expired'])
        ->and($payload['time_limit_minutes'])->toBe(45)
        ->and($payload['deadline_at'])->not->toBeNull()
        ->and($payload['seconds_remaining'])->toBeGreaterThan(39 * 60)
        ->and($payload['expired'])->toBeFalse();
});

it('carries the time limit through the settings the submit path reads', function () {
    $assessment = timedAssessment(25);

    // The root cause: the submit path resolves settings through this action,
    // and the field simply never arrived.
    $settings = app(\App\Domains\Courses\Actions\ResolveAssessmentSettingsAction::class)
        ->execute($assessment->id);

    expect($settings)->toHaveKey('time_limit_minutes')
        ->and($settings['time_limit_minutes'])->toBe(25);
});
