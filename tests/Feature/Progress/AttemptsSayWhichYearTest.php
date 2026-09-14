<?php

use App\Domains\Courses\Actions\AuthorizeActivityAccessAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Actions\ListPendingReviewsAction;
use App\Domains\Progress\Actions\SubmitActivityAttemptAction;
use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * An attempt records which year it happened in.
 *
 * ## The defect
 *
 * `activity_attempts` and `assessment_attempts` both carry `academic_year_id`,
 * which rule 10 requires of anything recording something that happens in time.
 * **Both writers passed a hardcoded `null`** —
 * `AuthorizeActivityAccessAction` and one branch of
 * `AuthorizeAssessmentAccessAction` — so every attempt ever written was
 * yearless. The column existed to satisfy the rule and nothing filled it.
 *
 * The other half was on the reading side: `ListPendingReviewsAction` accepted
 * an `academic_year_id` filter in its signature, documented it, and **silently
 * dropped it**. A caller narrowing a review queue to one year got every year
 * back.
 *
 * Neither half could be caught by the other. With no year on any row the
 * filter had nothing to narrow, and with no filter the empty column was never
 * read — which is why both survived. They are fixed together for that reason.
 *
 * ## How it was found
 *
 * Not by reading the code. `scripts/smoke/learn.mjs` walked a student through
 * answering an activity, and the attempt it produced had `academic_year_id`
 * null. That looked like the fixture's fault — the seeded enrolment carries no
 * term — until the writer turned out to hardcode it.
 */
function anAnsweredActivity(): array
{
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $course = App\Domains\Courses\Models\Course::query()->create([
        'course_category_id' => Illuminate\Support\Facades\DB::table('course_categories')->insertGetId([
            'name' => 'Attempts', 'slug' => 'attempts-'.Illuminate\Support\Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => 'Attempt course',
        'slug' => 'attempt-course-'.Illuminate\Support\Str::random(6),
        'short_desc' => 'x', 'body' => 'y', 'cover_image' => '',
        'workflow_status' => 'published', 'course_type' => 'general', 'status' => 'open',
    ]);
    $student = makeStudent(['first_name' => 'Answering']);

    $user = User::factory()->create();
    $student->forceFill(['user_id' => $user->id])->save();

    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => app(App\Domains\People\Actions\EnsureLegacyStudentForUnifiedAction::class)->execute($student->id),
        'unified_student_id' => $student->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]);

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Sun letters',
        'pattern' => 'selection',
        'data' => [
            'prompt' => 'Which is a sun letter?',
            'options' => [['id' => 'a', 'label' => 'ت'], ['id' => 'b', 'label' => 'ب']],
            'correct_ids' => ['a'],
        ],
    ]);

    return [$year, $user, $activity, $enrollment, $student];
}

it('stamps an activity attempt with the year it happened in', function () {
    [$year, $user, $activity, , $student] = anAnsweredActivity();

    $access = app(AuthorizeActivityAccessAction::class)->execute((int) $activity->id, (int) $user->id);

    expect($access['academic_year_id'])->toBe((int) $year->id);

    app(SubmitActivityAttemptAction::class)->execute(
        $access['activity_id'],
        $access['enrollment_id'],
        $access['student_id'],
        $access['course_id'],
        ['selected_ids' => ['a']],
        $access['academic_year_id'],
    );

    $attempt = ActivityAttempt::query()->where('student_id', $student->id)->firstOrFail();

    expect((int) $attempt->academic_year_id)->toBe((int) $year->id);
});

it('narrows the review queue to the year it was asked for', function () {
    [$year, $user, $activity] = anAnsweredActivity();

    $access = app(AuthorizeActivityAccessAction::class)->execute((int) $activity->id, (int) $user->id);

    // Submitted but not auto-scorable, so it lands in the review queue:
    // a teacher-marked answer to a selection activity is left pending by
    // sending no selection at all.
    app(SubmitActivityAttemptAction::class)->execute(
        $access['activity_id'],
        $access['enrollment_id'],
        $access['student_id'],
        $access['course_id'],
        ['selected_ids' => ['a']],
        $access['academic_year_id'],
    );

    ActivityAttempt::query()->update(['status' => 'submitted']);

    // A year the attempt did not happen in. Dated far away so it cannot also
    // match today, which is what the resolver keys on.
    $otherYear = makeYear([
        'name' => '2099-2100',
        'start_date' => '2099-01-01',
        'end_date' => '2099-12-31',
        'is_current' => false,
    ]);

    expect(app(ListPendingReviewsAction::class)->execute(['academic_year_id' => $year->id]))
        ->toHaveCount(1);

    // The half that was silently dropped: before this, asking for a different
    // year returned the same row.
    expect(app(ListPendingReviewsAction::class)->execute(['academic_year_id' => $otherYear->id]))
        ->toHaveCount(0);
});
