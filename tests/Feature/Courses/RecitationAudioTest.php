<?php

use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Quran\Actions\ReviewRecitationAction;
use App\Domains\Courses\Components\Quran\Actions\SubmitRecitationAction;
use App\Domains\Courses\Components\Quran\Models\QuranRecitationSubmission;
use App\Domains\Courses\Components\Quran\Models\Surah;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §36 lists, among what a teacher/reviewer must be able to do:
 *
 *   > Play audio/voice submissions … Upload correction audio
 *
 * Neither existed. `RecitationQueue.jsx` was 130 lines with no audio in it at
 * all, and `ListRecitationReviewQueueAction` did not even put the audio id in
 * the payload — so a teacher picked mistake types, severities and a pass/fail
 * outcome from dropdowns **without ever hearing the student recite**. For a
 * Qur'an institute that is not review.
 *
 * There was also nowhere to put a correction. Tajweed is a sound: "your madd is
 * short on ayah 4" describes the correction; three seconds of the teacher
 * reciting it is the correction.
 */
uses(RefreshDatabase::class);

function seedRecitationAudioFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear();
    $studentUser = User::factory()->create();
    $student = makeStudent(['user_id' => $studentUser->id, 'first_name' => 'Aminath']);
    $teacher = makeTeacherRow();
    $teacherUser = User::query()->findOrFail($teacher->user_id);
    $surah = Surah::query()->create([
        'index' => 1, 'arabic_name' => 'الفاتحة', 'english_name' => 'Al-Fatihah',
        'transliteration' => 'Al-Fatihah', 'ayah_count' => 7, 'revelation_place' => 'Meccan',
        'juz_start' => 1, 'juz_end' => 1, 'is_active' => true,
    ]);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Hifz engine course',
        'course_type' => 'hifz',
        'subject_id' => CourseSubject::query()->where('slug', 'hifz')->value('id'),
        'created_by' => $admin->id,
    ]);
    $offering = CourseOffering::query()->create([
        'course_id' => $course->id, 'title' => 'Hifz offering', 'slug' => 'hifz-audio-offering',
        'delivery_mode' => 'face_to_face', 'status' => 'open', 'pin_mode' => 'latest',
        'created_by' => $admin->id,
    ]);
    app(EnrollUnifiedStudentInOfferingAction::class)->execute($student->id, $course->id, $offering->id);

    // The student's recitation, stored the way an upload would store it.
    $audio = app(StorePrivateMediaAction::class)->execute(
        UploadedFile::fake()->create('recitation.mp3', 12, 'audio/mpeg'),
        $studentUser->id,
    );

    $submission = app(SubmitRecitationAction::class)->execute([
        'student_id' => $student->id,
        'surah_id' => $surah->id,
        'start_ayah_number' => 1,
        'end_ayah_number' => 7,
        'academic_year_id' => $year->id,
        'audio_media_file_id' => $audio['id'],
    ]);

    return compact('admin', 'year', 'studentUser', 'student', 'teacher', 'teacherUser', 'surah', 'submission', 'audio');
}

it('tells the review queue there is audio to play', function () {
    $ctx = seedRecitationAudioFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('teach.recitations'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Teach/RecitationQueue')
            // The payload carried no audio at all before this, which is why the
            // screen could not have played it.
            ->where('rows.0.has_audio', true)
            ->where('rows.0.has_correction_audio', false)
        );
});

it('plays the student recitation to a reviewing teacher', function () {
    $ctx = seedRecitationAudioFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('recitations.audio', ['submission' => $ctx['submission']->id, 'kind' => 'submission']))
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg');
});

it('attaches a correction recording to the review', function () {
    $ctx = seedRecitationAudioFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.recitations.review', ['submission' => $ctx['submission']->id]), [
            'status' => 'needs_repeat',
            'note' => 'The madd is short on ayah 4 — listen to mine.',
            'correction_audio' => UploadedFile::fake()->create('correction.mp3', 30, 'audio/mpeg'),
        ])->assertRedirect();

    $submission = QuranRecitationSubmission::query()->findOrFail($ctx['submission']->id);
    expect($submission->correction_audio_media_file_id)->not->toBeNull();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('recitations.audio', ['submission' => $submission->id, 'kind' => 'correction']))
        ->assertOk();
});

it('keeps an existing correction when a teacher re-reviews without recording again', function () {
    $ctx = seedRecitationAudioFixture();

    app(ReviewRecitationAction::class)->execute($ctx['submission']->id, [
        'status' => 'needs_repeat',
        'teacher_id' => $ctx['teacher']->id,
        'reviewed_by' => $ctx['teacherUser']->id,
        'correction_audio_media_file_id' => $ctx['audio']['id'],
    ]);

    app(ReviewRecitationAction::class)->execute($ctx['submission']->id, [
        'status' => 'passed',
        'teacher_id' => $ctx['teacher']->id,
        'reviewed_by' => $ctx['teacherUser']->id,
    ]);

    // Losing the recording because the outcome was corrected would silently
    // throw away the most useful part of the feedback.
    expect(QuranRecitationSubmission::query()->findOrFail($ctx['submission']->id)->correction_audio_media_file_id)
        ->toBe((int) $ctx['audio']['id']);
});

it('lets the student play their own recitation and the correction', function () {
    $ctx = seedRecitationAudioFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.recitations.review', ['submission' => $ctx['submission']->id]), [
            'status' => 'needs_repeat',
            'correction_audio' => UploadedFile::fake()->create('correction.mp3', 30, 'audio/mpeg'),
        ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['studentUser'])
        ->get(route('learn.quran'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.has_audio', true)
            // A correction the student cannot play is not feedback.
            ->where('submissions.0.has_correction_audio', true)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['studentUser'])
        ->get(route('recitations.audio', ['submission' => $ctx['submission']->id, 'kind' => 'correction']))
        ->assertOk();
});

it('refuses to play one student recitation to another student', function () {
    $ctx = seedRecitationAudioFixture();
    $otherUser = User::factory()->create();
    makeStudent(['user_id' => $otherUser->id, 'first_name' => 'Someone', 'last_name' => 'Else']);

    // These are recordings of a named child's voice. The catalogue media path
    // authorizes by asking whether a file appears in a lesson, which would have
    // been the wrong question entirely.
    $this->withoutLocalizationMiddleware()
        ->actingAs($otherUser)
        ->get(route('recitations.audio', ['submission' => $ctx['submission']->id, 'kind' => 'submission']))
        ->assertForbidden();
});

it('refuses to play a recitation to a signed-out visitor', function () {
    $ctx = seedRecitationAudioFixture();

    $this->withoutLocalizationMiddleware()
        ->get(route('recitations.audio', ['submission' => $ctx['submission']->id, 'kind' => 'submission']))
        ->assertRedirect();
});

it('404s when the requested recording does not exist', function () {
    $ctx = seedRecitationAudioFixture();

    // No correction has been recorded yet.
    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->get(route('recitations.audio', ['submission' => $ctx['submission']->id, 'kind' => 'correction']))
        ->assertNotFound();
});

it('rejects a correction upload that is not audio', function () {
    $ctx = seedRecitationAudioFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($ctx['teacherUser'])
        ->post(route('teach.recitations.review', ['submission' => $ctx['submission']->id]), [
            'status' => 'passed',
            'correction_audio' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('correction_audio');

    // And the review did not go through on the back of a rejected upload: a
    // half-applied review would be worse than a refused one.
    $submission = QuranRecitationSubmission::query()->findOrFail($ctx['submission']->id);
    expect($submission->correction_audio_media_file_id)->toBeNull()
        ->and($submission->status?->value)->not->toBe('passed');
});
