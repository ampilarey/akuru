<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\ActivitySubmissionKind;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Actions\SubmitActivityAttemptAction;
use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

/**
 * SPEC §36 "Teacher / Instructor / Reviewer Dashboard" names thirteen
 * abilities. Three are about what the student handed in:
 *
 *   > Open student submissions · Play audio/voice submissions · View uploaded files
 *
 * All three rested on a submission kind the student side could not produce.
 * `SaveActivityAction` validated and stored `submission_kind` for a
 * teacher-marked activity — and **nothing read it**. The player rendered a
 * `<textarea>` whichever kind the author chose, no route accepted a file
 * against an attempt, and the review screen answered all three of §36's lines
 * with `JSON.stringify(row.answers)` inside a collapsed `<details>`.
 *
 * So an author could set `file`, the value round-tripped through the database
 * intact, and the student was still shown a text box. The same taxonomy as §20
 * and §22: a three-link chain where fixing any one link alone leaves the
 * column as dead as it was.
 *
 * The old allowlist also had no `audio`, though §36 names it explicitly.
 */
uses(RefreshDatabase::class);

function submissionCourse(): object
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Submission '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return $course->fresh();
}

function submissionActivity(int $courseId, string $kind): object
{
    return app(SaveActivityAction::class)->execute([
        'course_id' => $courseId,
        'title' => 'Hand it in',
        'pattern' => 'teacher_marked',
        'activity_type' => 'assignment',
        'max_score' => 10,
        'data' => ['prompt' => 'Record yourself', 'submission_kind' => $kind],
    ]);
}

function submissionStudent(int $courseId): array
{
    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Hand', 'last_name' => 'In']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $courseId, null);

    return compact('user', 'student', 'enrollment');
}

it('saves a teacher-marked activity without a submission kind', function () {
    // The old line read `$data['submission_kind']` in the branch its own
    // `?? 'written'` was meant to guard, so the default path — which every
    // caller in the codebase takes — raised "Undefined array key".
    $course = submissionCourse();

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'No kind given',
        'pattern' => 'teacher_marked',
        'activity_type' => 'assignment',
        'data' => ['prompt' => 'Write something'],
    ]);

    expect($activity->data['submission_kind'])->toBe('written');
});

it('keeps audio, which §36 names and the old allowlist had no room for', function () {
    $course = submissionCourse();
    $activity = submissionActivity($course->id, 'audio');

    expect($activity->data['submission_kind'])->toBe('audio');
    expect(ActivitySubmissionKind::Audio->allowedMimes())->toContain('audio/mpeg');
    // Audio is deliberately narrow: §36 asks the teacher to *play* it.
    expect(ActivitySubmissionKind::Audio->allowedMimes())->not->toContain('application/pdf');
    expect(ActivitySubmissionKind::File->allowedMimes())->toContain('application/pdf');
    // An unknown kind falls back rather than storing a value nothing can read.
    expect(ActivitySubmissionKind::fromValue('sculpture'))->toBe(ActivitySubmissionKind::Written);
});

it('tells the player which submission the activity actually wants', function () {
    $course = submissionCourse();
    $activity = submissionActivity($course->id, 'audio');
    ['user' => $user] = submissionStudent($course->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get('/learn/activities/'.$activity->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activity.submission.kind', 'audio')
            ->where('activity.submission.accepts_uploads', true)
            // An audio activity is not a text box wearing a different label.
            ->where('activity.submission.accepts_text', false));
});

it('stores an uploaded recording against the attempt', function () {
    $course = submissionCourse();
    $activity = submissionActivity($course->id, 'audio');
    ['user' => $user, 'enrollment' => $enrollment] = submissionStudent($course->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->post('/learn/activities/'.$activity->id.'/upload', [
            'file' => UploadedFile::fake()->create('answer.mp3', 12, 'audio/mpeg'),
        ])
        ->assertRedirect();

    $attempt = ActivityAttempt::query()->where('enrollment_id', $enrollment->id)->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->answers['attachments'])->toHaveCount(1);
    expect($attempt->answers['attachments'][0]['mime'])->toBe('audio/mpeg');
    expect($attempt->answers['attachments'][0]['original_name'])->toBe('answer.mp3');
});

it('refuses a PDF handed to an audio activity', function () {
    $course = submissionCourse();
    $activity = submissionActivity($course->id, 'audio');
    ['user' => $user] = submissionStudent($course->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->post('/learn/activities/'.$activity->id.'/upload', [
            'file' => UploadedFile::fake()->create('essay.pdf', 12, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');
});

it('refuses an upload to a written activity', function () {
    $course = submissionCourse();
    $activity = submissionActivity($course->id, 'written');
    ['user' => $user] = submissionStudent($course->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->post('/learn/activities/'.$activity->id.'/upload', [
            'file' => UploadedFile::fake()->create('answer.mp3', 12, 'audio/mpeg'),
        ])
        ->assertForbidden();
});

it('carries the upload into the submitted attempt the reviewer sees', function () {
    $course = submissionCourse();
    $activity = submissionActivity($course->id, 'audio');
    ['user' => $user, 'student' => $student, 'enrollment' => $enrollment] = submissionStudent($course->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->post('/learn/activities/'.$activity->id.'/upload', [
            'file' => UploadedFile::fake()->create('answer.mp3', 12, 'audio/mpeg'),
        ]);

    // The browser posts `answers` back on submit. It does not know the
    // attachment list unless the server put it there.
    $result = app(SubmitActivityAttemptAction::class)->execute(
        $activity->id,
        $enrollment->id,
        (int) $student->id,
        $course->id,
        [],
    );

    expect($result['attempt']['answers']['attachments'])->toHaveCount(1);

    $admin = actingPeopleAdmin(['courses.manage']);
    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/reviews')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('rows.0.answers.attachments', 1));
});

it('lets a client remove an attachment but never add one', function () {
    // The whole `answers` object comes back from the browser on every save, so
    // without a server-owned list a client could name any media id and have a
    // teacher handed a link to it.
    $course = submissionCourse();
    $activity = submissionActivity($course->id, 'audio');
    ['user' => $user, 'student' => $student, 'enrollment' => $enrollment] = submissionStudent($course->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->post('/learn/activities/'.$activity->id.'/upload', [
            'file' => UploadedFile::fake()->create('answer.mp3', 12, 'audio/mpeg'),
        ]);

    $stored = (int) ActivityAttempt::query()
        ->where('enrollment_id', $enrollment->id)
        ->first()->answers['attachments'][0]['id'];

    $forged = app(SubmitActivityAttemptAction::class)->execute(
        $activity->id,
        $enrollment->id,
        (int) $student->id,
        $course->id,
        ['attachments' => [
            ['id' => $stored, 'mime' => 'audio/mpeg', 'original_name' => 'answer.mp3'],
            ['id' => $stored + 9999, 'mime' => 'audio/mpeg', 'original_name' => 'someone-elses.mp3'],
        ]],
    );

    $ids = array_column($forged['attempt']['answers']['attachments'], 'id');
    expect($ids)->toBe([$stored]);
});

it('drops an attachment the student removed', function () {
    $course = submissionCourse();
    $activity = submissionActivity($course->id, 'audio');
    ['user' => $user, 'enrollment' => $enrollment] = submissionStudent($course->id);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->post('/learn/activities/'.$activity->id.'/upload', [
            'file' => UploadedFile::fake()->create('answer.mp3', 12, 'audio/mpeg'),
        ]);

    $stored = (int) ActivityAttempt::query()
        ->where('enrollment_id', $enrollment->id)
        ->first()->answers['attachments'][0]['id'];

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->delete('/learn/activities/'.$activity->id.'/attachments/'.$stored)
        ->assertRedirect();

    $attempt = ActivityAttempt::query()->where('enrollment_id', $enrollment->id)->first();
    expect($attempt->answers['attachments'])->toBe([]);
});
