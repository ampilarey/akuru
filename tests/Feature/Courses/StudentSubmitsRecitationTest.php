<?php

use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Quran\Models\QuranRecitationSubmission;
use App\Domains\Courses\Components\Quran\Models\Surah;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * SPEC §52.9, the manual recording mode:
 *
 *   > 1. Student clicks Record. … 5. Student clicks Submit.
 *   > 6. Laravel stores audio permanently using private media storage.
 *   > 9. Teacher/supervisor/dean/admin can review later.
 *
 * Steps 6–9 shipped with F3. **Steps 1–5 had no route.**
 * `SubmitRecitationAction` was reachable from six test files and from nothing a
 * student could press, so `quran_recitation_submissions` could only ever be
 * empty and the teacher's review queue — audio playback, mistake marking,
 * outcomes, CSV export, the AI-opinion column — had nothing to review.
 *
 * F4 recorded the deferral honestly ("needs private media upload +
 * authenticated streaming"); both shipped afterwards and nothing went back for
 * it. These tests are the way in.
 */
function studentRecitationFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $studentUser = User::factory()->create();
    $student = makeStudent(['user_id' => $studentUser->id, 'first_name' => 'Aminath']);

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
        'course_id' => $course->id, 'title' => 'Hifz offering', 'slug' => 'hifz-submit-offering',
        'delivery_mode' => 'face_to_face', 'status' => 'open', 'pin_mode' => 'latest',
        'created_by' => $admin->id,
    ]);
    // `SubmitRecitationAction` resolves the engine enrolment and refuses
    // without one, so the student has to be on something.
    app(EnrollUnifiedStudentInOfferingAction::class)->execute($student->id, $course->id, $offering->id);

    return compact('admin', 'studentUser', 'student', 'surah');
}

$recording = fn (): UploadedFile => UploadedFile::fake()->create('recitation.webm', 20, 'audio/webm');

it('lets a student record a recitation and hands it to the teacher', function () use ($recording) {
    ['studentUser' => $studentUser, 'student' => $student, 'surah' => $surah] = studentRecitationFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->post(route('learn.quran.recitations.store'), [
            'surah_id' => $surah->id,
            'start_ayah_number' => 1,
            'end_ayah_number' => 7,
            'audio' => $recording(),
        ])
        ->assertRedirect();

    $submission = QuranRecitationSubmission::query()->firstOrFail();

    expect((int) $submission->student_id)->toBe((int) $student->id)
        ->and((int) $submission->surah_id)->toBe((int) $surah->id)
        ->and($submission->status->value)->toBe('submitted')
        ->and($submission->submitted_at)->not->toBeNull()
        // The engine seam, resolved rather than posted — a student cannot
        // choose which enrolment their recitation counts against.
        ->and($submission->course_enrollment_id)->not->toBeNull();

    // §52.9 step 6, and the sentence after it: "Manual recordings must be
    // protected as private media."
    $media = MediaFile::query()->findOrFail($submission->audio_media_file_id);
    expect($media->visibility)->toBe('private');
});

it('refuses a submission with no audio in it', function () {
    ['studentUser' => $studentUser, 'surah' => $surah] = studentRecitationFixture();

    // A teacher being asked to pass or fail a student they cannot hear is the
    // thing this rule exists to prevent.
    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->post(route('learn.quran.recitations.store'), [
            'surah_id' => $surah->id,
            'start_ayah_number' => 1,
        ])
        ->assertSessionHasErrors('audio');

    expect(QuranRecitationSubmission::query()->count())->toBe(0);
});

it('refuses a file that is not audio', function () {
    ['studentUser' => $studentUser, 'surah' => $surah] = studentRecitationFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->post(route('learn.quran.recitations.store'), [
            'surah_id' => $surah->id,
            'start_ayah_number' => 1,
            'audio' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors('audio');

    expect(QuranRecitationSubmission::query()->count())->toBe(0);
});

it('refuses an ayah range that runs backwards', function () use ($recording) {
    ['studentUser' => $studentUser, 'surah' => $surah] = studentRecitationFixture();

    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->post(route('learn.quran.recitations.store'), [
            'surah_id' => $surah->id,
            'start_ayah_number' => 7,
            'end_ayah_number' => 2,
            'audio' => $recording(),
        ])
        ->assertSessionHasErrors('end_ayah_number');
});

it('refuses a surah that does not exist', function () use ($recording) {
    ['studentUser' => $studentUser] = studentRecitationFixture();

    // Validated through the Qur'an reference contract inside the Action, which
    // is the one dataset (rule 11) — not a second list kept beside it.
    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->post(route('learn.quran.recitations.store'), [
            'surah_id' => 9999,
            'start_ayah_number' => 1,
            'audio' => $recording(),
        ])
        ->assertSessionHasErrors('surah_id');

    expect(QuranRecitationSubmission::query()->count())->toBe(0);
});

it('will not let somebody with no student profile submit', function () use ($recording) {
    ['surah' => $surah] = studentRecitationFixture();
    $stranger = User::factory()->create();

    $this->withoutLocalizationMiddleware()
        ->actingAs($stranger)
        ->post(route('learn.quran.recitations.store'), [
            'surah_id' => $surah->id,
            'start_ayah_number' => 1,
            'audio' => $recording(),
        ])
        ->assertForbidden();
});

it('offers the student the surahs to choose from', function () {
    ['studentUser' => $studentUser] = studentRecitationFixture();

    // The picker cannot be filled in from nothing, and an empty list is how
    // this screen would silently lose its recorder.
    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->get(route('learn.quran'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Quran')
            ->has('surahs', 1)
            ->where('surahs.0.name', 'Al-Fatihah')
            ->where('surahs.0.ayah_count', 7));
});
