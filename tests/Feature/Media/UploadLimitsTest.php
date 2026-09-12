<?php

use App\Domains\Courses\Actions\StoreMediaContentBlockAction;
use App\Domains\Courses\Enums\ContentBlockType;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Pronunciation\Actions\StoreArabicPronunciationAttemptAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §30 "Upload Validation":
 *
 *   > Images: max 5MB, jpg/png/webp · Audio: max 20MB, mp3/m4a/ogg/webm ·
 *   > Video: max 200MB, mp4/webm · PDFs: max 25MB ·
 *   > Student voice recordings: max 10MB
 *   >
 *   > Reject uploads by MIME validation/sniffing, not extension only.
 *
 * Every media content block shared one blanket 50MB cap, and student voice
 * recordings were stored with no type check at all.
 *
 * These tests write real bytes rather than using `UploadedFile::fake()` with a
 * declared MIME. `fake()` sets the MIME directly, so a test using it passes
 * whether or not sniffing works — which is exactly how an earlier slice in
 * this repo shipped nine green tests over uploads a browser could not make.
 */
uses(RefreshDatabase::class);

function realPng(int $padBytes = 0): UploadedFile
{
    // A 1x1 PNG. The signature is what `getMimeType()` sniffs.
    $bytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
    $path = tempnam(sys_get_temp_dir(), 'png').'.png';
    file_put_contents($path, $bytes.str_repeat("\0", $padBytes));

    return new UploadedFile($path, 'shot.png', null, null, true);
}

function realPdf(int $padBytes = 0): UploadedFile
{
    $bytes = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    file_put_contents($path, $bytes.str_repeat("\n", $padBytes));

    return new UploadedFile($path, 'notes.pdf', null, null, true);
}

function realWav(int $padBytes = 0): UploadedFile
{
    $data = str_repeat("\0", 64 + $padBytes);
    $bytes = 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '
        .pack('V', 16).pack('v', 1).pack('v', 1)
        .pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8)
        .'data'.pack('V', strlen($data)).$data;
    $path = tempnam(sys_get_temp_dir(), 'wav').'.wav';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, 'recording.wav', null, null, true);
}

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
});

it('gives each media kind the limit SPEC §30 sets for it', function () {
    expect(ContentBlockType::Image->maxBytes())->toBe(5 * 1024 * 1024)
        ->and(ContentBlockType::Audio->maxBytes())->toBe(20 * 1024 * 1024)
        ->and(ContentBlockType::Video->maxBytes())->toBe(200 * 1024 * 1024)
        ->and(ContentBlockType::Pdf->maxBytes())->toBe(25 * 1024 * 1024);
});

it('leaves non-media blocks without a file limit', function () {
    expect(ContentBlockType::Text->maxBytes())->toBeNull()
        ->and(ContentBlockType::QuizEmbed->maxBytes())->toBeNull();
});

it('reports the video allowance as the outer bound for request rules', function () {
    // The request rule runs before the block type is known, so it can only
    // enforce the largest. Pinning it stops the rule drifting from the enum.
    expect(ContentBlockType::largestMaxBytes())->toBe(200 * 1024 * 1024);
});

it('refuses an image over 5MB', function () {
    // The blanket cap was 50MB, so this was accepted at ten times the
    // allowance §30 gives an image.
    $file = realPng(6 * 1024 * 1024);

    expect(fn () => app(StorePrivateMediaAction::class)->execute(
        $file,
        null,
        ContentBlockType::Image->allowedMimes(),
        ContentBlockType::Image->maxBytes(),
    ))->toThrow(ValidationException::class);
});

it('accepts an image under 5MB', function () {
    $stored = app(StorePrivateMediaAction::class)->execute(
        realPng(1024),
        null,
        ContentBlockType::Image->allowedMimes(),
        ContentBlockType::Image->maxBytes(),
    );

    expect($stored['mime'])->toBe('image/png');
});

it('enforces the limit through the block action, not only the request', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(\App\Domains\Courses\Actions\SaveEngineCourseAction::class)->execute([
        'title' => 'Media limits',
        'subject_id' => \App\Domains\Courses\Models\CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(\App\Domains\Courses\Actions\SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit', 'created_by' => $admin->id,
    ]);
    $lesson = app(\App\Domains\Courses\Actions\SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Lesson', 'created_by' => $admin->id,
    ]);

    expect(fn () => app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'image',
        'file' => realPng(6 * 1024 * 1024),
    ]))->toThrow(ValidationException::class);
});

it('names the limit in the message rather than failing anonymously', function () {
    try {
        app(StorePrivateMediaAction::class)->execute(
            realPdf(0),
            null,
            [],
            10,
        );
        $this->fail('Expected the size check to reject.');
    } catch (ValidationException $e) {
        expect($e->errors()['file'][0])->toContain('MB');
    }
});

it('rejects a file whose contents do not match the block type', function () {
    // Sniffed, not trusted: the name says .png and the bytes say PDF.
    $pdf = realPdf();

    expect(fn () => app(StorePrivateMediaAction::class)->execute(
        $pdf,
        null,
        ContentBlockType::Image->allowedMimes(),
        ContentBlockType::Image->maxBytes(),
    ))->toThrow(ValidationException::class);
});

it('refuses a student voice recording that is not audio', function () {
    // This path passed no allow-list at all, and the action skips the check
    // when the list is empty — so any file up to 10MB was stored under the
    // student's name and handed to a teacher to open.
    $student = makeStudent(['first_name' => 'Voice', 'last_name' => 'Student']);

    expect(fn () => app(StoreArabicPronunciationAttemptAction::class)->execute(
        (int) $student->user_id,
        ['expected_letter_id' => 1, 'expected_haraka_id' => 1],
        realPdf(),
    ))->toThrow(ValidationException::class);
});

it('still accepts a genuine student voice recording', function () {
    $student = makeStudent(['first_name' => 'Voice', 'last_name' => 'Ok']);

    $attempt = app(StoreArabicPronunciationAttemptAction::class)->execute(
        (int) $student->user_id,
        ['expected_letter_id' => 1, 'expected_haraka_id' => 1],
        realWav(),
    );

    expect($attempt->audio_media_file_id)->not->toBeNull();
});

it('caps student voice recordings at the 10MB SPEC §30 sets', function () {
    expect(StoreArabicPronunciationAttemptAction::MAX_BYTES)->toBe(10 * 1024 * 1024);

    $student = makeStudent(['first_name' => 'Voice', 'last_name' => 'Long']);

    expect(fn () => app(StoreArabicPronunciationAttemptAction::class)->execute(
        (int) $student->user_id,
        ['expected_letter_id' => 1, 'expected_haraka_id' => 1],
        realWav(11 * 1024 * 1024),
    ))->toThrow(ValidationException::class);
});

it('accepts the webm a browser recorder actually produces', function () {
    // MediaRecorder emits `video/webm` on Chromium even for an audio-only
    // stream. §30 requires the recorder path to work, so the allow-list has
    // to admit it.
    expect(StoreArabicPronunciationAttemptAction::ALLOWED_AUDIO_MIMES)
        ->toContain('audio/webm')
        ->toContain('video/webm');
});

it('flashes a validation error the outline screen can render', function () {
    // The refusal must reach the session, not just prevent the write. Proven
    // here because the browser walk could not confirm the screen *renders* it
    // — see STATUS; that is an open question about the outline form, not about
    // this rule.
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(\App\Domains\Courses\Actions\SaveEngineCourseAction::class)->execute([
        'title' => 'Upload errors',
        'subject_id' => \App\Domains\Courses\Models\CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(\App\Domains\Courses\Actions\SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit', 'created_by' => $admin->id,
    ]);
    $lesson = app(\App\Domains\Courses\Actions\SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Lesson', 'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks", [
            'lesson_id' => $lesson->id,
            'type' => 'image',
            'file' => realPng(6 * 1024 * 1024),
        ])
        ->assertSessionHasErrors('file');
});

it('stores an upload with no limit given, as before', function () {
    // `maxBytes` is optional; callers that pass nothing keep their behaviour.
    $stored = app(StorePrivateMediaAction::class)->execute(realPng(1024));

    expect($stored['visibility'])->toBe('private');
});
