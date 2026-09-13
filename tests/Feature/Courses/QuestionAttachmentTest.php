<?php

use App\Domains\Courses\Actions\ResolveQuestionMediaAction;
use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Courses\Actions\SnapshotQuestionAction;
use App\Domains\Courses\Enums\ContentBlockType;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Question;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §20 "Question Attachments":
 *
 *   > Questions may have: Audio · Image · PDF · Video reference
 *   > All attachments must go through the centralized media system.
 *
 * Half of that shipped and the half that shipped was the half nobody sees.
 *
 * The upload worked: the bank has a file input, `SaveQuestionAction` funnels it
 * through `StorePrivateMediaAction`, and §21's snapshot copied the reference
 * into every attempt. Then:
 *
 * - **Nothing rendered it.** `attachments` appears in three PHP files and zero
 *   React ones, so `audio` and `image` — two of §20's twelve question types —
 *   showed the student the text and nothing else. An audio question with no
 *   audio is not a hard question; it is an unanswerable one, and it looks like
 *   an ordinary page.
 * - **Nothing would have served it.** `ServeCatalogMediaAction` admitted a
 *   student only for media inside a *lesson* content block, so even a player
 *   that drew the tag would have drawn a 403.
 * - **Nothing checked it.** This was the one upload path in the app that passed
 *   neither an allowed-mime list nor a size cap to the media store, while §30
 *   defines both per kind — and `ContentBlockType` already holds them.
 * - **Editing destroyed it.** `attachments` defaulted to `[]` when the payload
 *   omitted the key, and the controller's payload never sends it, so saving any
 *   edit through `PUT catalog/questions/{question}` silently dropped every file
 *   the question had.
 *
 * That last one had no way to fire, which is its own finding: the PUT route and
 * its controller method both existed and **no screen called them**. The bank
 * was write-once.
 */
uses(RefreshDatabase::class);

function qbMediaAdmin(): User
{
    return actingPeopleAdmin(['courses.manage']);
}

function qbMediaQuestion(array $overrides = []): Question
{
    return app(SaveQuestionAction::class)->execute(array_merge([
        'question_type' => 'audio',
        'question_text' => 'What word do you hear?',
        'options' => [['id' => 'a', 'label' => 'Kitab'], ['id' => 'b', 'label' => 'Qalam']],
        'correct_answer' => ['a'],
    ], $overrides));
}

it('knows which of §20 four kinds a mime belongs to', function () {
    $media = app(ResolveQuestionMediaAction::class);

    expect($media->kindForMime('audio/mpeg'))->toBe(ContentBlockType::Audio)
        ->and($media->kindForMime('image/png'))->toBe(ContentBlockType::Image)
        ->and($media->kindForMime('application/pdf'))->toBe(ContentBlockType::Pdf)
        ->and($media->kindForMime('video/mp4'))->toBe(ContentBlockType::Video)
        // §20 gives a question four kinds. A spreadsheet is not one of them.
        ->and($media->kindForMime('application/vnd.ms-excel'))->toBeNull();
});

it('refuses a question attachment that is none of §20 four kinds', function () {
    Storage::fake('local');

    // Previously stored without complaint: this call passed no allowed-mime
    // list at all, so any file at all was a valid question attachment.
    expect(fn () => qbMediaQuestion([
        'file' => UploadedFile::fake()->create('payload.bin', 8, 'application/octet-stream'),
    ]))->toThrow(ValidationException::class);

    expect(Question::query()->count())->toBe(0);
});

it('holds a question attachment to §30 size cap for its kind', function () {
    Storage::fake('local');

    // §30: "Images: max 5MB". The cap lives on ContentBlockType and this path
    // never passed it, so a question image had no limit whatsoever.
    expect(fn () => qbMediaQuestion([
        'question_type' => 'image',
        'file' => UploadedFile::fake()->create('huge.png', 6 * 1024, 'image/png'),
    ]))->toThrow(ValidationException::class);

    $question = qbMediaQuestion([
        'question_type' => 'image',
        'file' => UploadedFile::fake()->create('small.png', 64, 'image/png'),
    ]);

    expect($question->attachments[0]['kind'])->toBe('image');
});

it('accepts a video reference and normalizes it to an embed', function () {
    // §20's fourth kind is a *reference*, not an upload — and it shares §15's
    // host allowlist rather than carrying a second copy of it.
    $question = qbMediaQuestion([
        'question_type' => 'audio',
        'video_url' => 'https://www.youtube.com/watch?v=abc123',
        'video_title' => 'The dialogue',
    ]);

    expect($question->attachments[0]['embed_url'])->toBe('https://www.youtube.com/embed/abc123')
        ->and($question->attachments[0]['kind'])->toBe('video');
});

it('refuses a video reference from a host §15 does not allow', function () {
    expect(fn () => qbMediaQuestion([
        'video_url' => 'https://videos.example.com/clip.mp4',
    ]))->toThrow(ValidationException::class);

    expect(fn () => qbMediaQuestion([
        'video_url' => 'http://www.youtube.com/watch?v=abc123',
    ]))->toThrow(ValidationException::class);
});

it('resolves every attachment into something a player can draw', function () {
    $resolved = app(ResolveQuestionMediaAction::class)->execute([
        ['media_id' => 7, 'mime' => 'audio/mpeg', 'original_name' => 'word.mp3'],
        ['embed_url' => 'https://player.vimeo.com/video/42'],
        // The Blade-era shape from the legacy quiz migration: a filesystem
        // path that never entered the media system at all. Returned with a
        // null media_id rather than dropped, so the gap shows on screen
        // instead of the question quietly losing its picture.
        ['path' => 'uploads/old.jpg', 'kind' => 'image'],
    ]);

    expect($resolved)->toHaveCount(3)
        ->and($resolved[0]['kind'])->toBe('audio')
        ->and($resolved[0]['media_id'])->toBe(7)
        ->and($resolved[1]['kind'])->toBe('video')
        ->and($resolved[1]['embed_url'])->toBe('https://player.vimeo.com/video/42')
        ->and($resolved[2]['kind'])->toBe('image')
        ->and($resolved[2]['media_id'])->toBeNull();

    expect(app(ResolveQuestionMediaAction::class)->mediaIds([
        ['media_id' => 7, 'mime' => 'audio/mpeg'],
        ['embed_url' => 'https://player.vimeo.com/video/42'],
    ]))->toBe([7]);
});

it('freezes resolved media into the attempt snapshot (§21)', function () {
    Storage::fake('local');
    $question = qbMediaQuestion(['file' => UploadedFile::fake()->create('word.mp3', 16, 'audio/mpeg')]);

    $snapshot = app(SnapshotQuestionAction::class)->execute($question);

    // §21: "Media references or resolved media metadata where appropriate."
    // The raw reference was already there; what was missing is the answer to
    // "is this a sound or a PDF", which the client must not re-derive.
    expect($snapshot['media'][0]['kind'])->toBe('audio')
        ->and($snapshot['media'][0]['media_id'])->toBe((int) $question->attachments[0]['media_id']);
});

it('keeps a question attachments when an edit does not mention them', function () {
    Storage::fake('local');
    $admin = qbMediaAdmin();
    $question = qbMediaQuestion(['file' => UploadedFile::fake()->create('word.mp3', 16, 'audio/mpeg')]);

    expect($question->attachments)->toHaveCount(1);

    // The controller's payload has never sent `attachments`, and the Action
    // defaulted the key to `[]`. Every edit wiped the files.
    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->put("/catalog/questions/{$question->id}", [
            'question_type' => 'audio',
            'question_text' => 'What word do you hear now?',
        ])
        ->assertRedirect();

    expect($question->fresh()->attachments)->toHaveCount(1)
        ->and($question->fresh()->question_text)->toBe('What word do you hear now?');
});

it('takes one attachment off without touching the others', function () {
    Storage::fake('local');
    $admin = qbMediaAdmin();
    $question = qbMediaQuestion(['file' => UploadedFile::fake()->create('one.mp3', 16, 'audio/mpeg')]);
    $question = app(SaveQuestionAction::class)->execute([
        'question_type' => 'audio',
        'question_text' => $question->question_text,
        'file' => UploadedFile::fake()->create('two.mp3', 16, 'audio/mpeg'),
    ], $question);

    expect($question->attachments)->toHaveCount(2);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->put("/catalog/questions/{$question->id}", [
            'question_type' => 'audio',
            'question_text' => $question->question_text,
            'remove_attachment' => 0,
        ])
        ->assertRedirect();

    $remaining = $question->fresh()->attachments;
    expect($remaining)->toHaveCount(1)
        ->and($remaining[0]['original_name'])->toBe('two.mp3');
});

it('lets the student sitting the question read its audio, and nobody else', function () {
    Storage::fake('local');
    $question = qbMediaQuestion(['file' => UploadedFile::fake()->create('word.mp3', 16, 'audio/mpeg')]);
    $mediaId = (int) $question->attachments[0]['media_id'];

    $student = makeStudent(['first_name' => 'Sitting', 'last_name' => 'Pupil']);
    $stranger = makeStudent(['first_name' => 'Other', 'last_name' => 'Pupil']);

    $assessment = Assessment::query()->create([
        'title' => 'Listening check',
        'status' => 'published',
        'max_score' => 10,
    ]);
    AssessmentAttempt::query()->create([
        'assessment_id' => $assessment->id,
        'student_id' => $student->id,
        'attempt_number' => 1,
        'status' => 'in_progress',
        'answers' => [],
        'snapshots' => [app(SnapshotQuestionAction::class)->execute($question)],
        'started_at' => now(),
        'last_saved_at' => now(),
    ]);

    // Before this slice the gate knew only about lesson content blocks, so the
    // student sitting the audio question was refused the audio.
    $this->actingAs(User::query()->find($student->user_id))
        ->withoutLocalizationMiddleware()
        ->get("/learn/media/{$mediaId}")
        ->assertOk();

    // Someone else's paper is still someone else's paper.
    $this->actingAs(User::query()->find($stranger->user_id))
        ->withoutLocalizationMiddleware()
        ->get("/learn/media/{$mediaId}")
        ->assertForbidden();
});

it('hands the assessment player the prefix it needs to build media URLs', function () {
    // The same media id is `/catalog/media/{id}` to an author and
    // `/learn/media/{id}` to a student, so the prefix is a prop — exactly as it
    // already is in the lesson player — never a URL frozen into a snapshot.
    $source = (string) file_get_contents(app_path('Domains/Courses/Http/Controllers/LearnAssessmentController.php'));

    expect($source)->toContain("'mediaShowUrl' => '/learn/media'");
});
