<?php

use App\Domains\Courses\Actions\AttachLessonGlossaryItemAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveGlossaryItemAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\StoreGlossaryMediaAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\GlossaryItem;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §22 "Glossary Media":
 *
 *   > Glossary items may have: Audio · Image · Example audio · Diagram
 *   > All media must use the centralized media system.
 *
 * Everything around those four columns was built. They exist on the table. The
 * model is fillable for them. `SaveGlossaryItemAction` writes them.
 * `GlossaryController` validates them properly against `media_files` — this is
 * one of the better-built controllers in the app, with no rule-5 gap at all.
 * And `GlossaryItem::toPayload()` sends all four to the lesson player.
 *
 * **Nothing ever uploaded one, and nothing ever drew one.** The admin form had
 * no file input of any kind, so there was no way to obtain a `media_files` id
 * to put in those columns — the `exists:media_files,id` rules guarded a door
 * nobody could reach. And the player's term panel drew term, transliteration,
 * meaning, description and example, and no media.
 *
 * For a bank whose first listed use is **Arabic vocabulary**, the missing half
 * is the wrong half: the pronunciation recording is what a term most needs, and
 * §22 lists it first.
 *
 * A third gate was shut behind those two. `ServeCatalogMediaAction` admitted a
 * student only for media inside a *lesson content block*, and glossary media
 * hangs off the **term**, reached through `lesson_glossary_items`. So even a
 * page that drew the audio would have drawn a 403 — exactly as it did for §20's
 * question attachments before that slice.
 */
uses(RefreshDatabase::class);

function glossaryMediaLesson(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Glossary media '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit 1', 'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Vowels', 'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text', 'data' => ['body' => 'The fatha is a short a.'],
        'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return ['admin' => $admin, 'course' => $course->fresh(), 'lesson' => $lesson->fresh()];
}

function glossaryMediaAudio(string $name = 'fatha.wav'): UploadedFile
{
    // A real WAV header, so `getMimeType()` sniffs audio rather than falling
    // back to the browser's claim — §30 requires sniffing, not extensions.
    $data = str_repeat(chr(127), 800);
    $wav = 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('V', 16).pack('v', 1).pack('v', 1)
        .pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8).'data'.pack('V', strlen($data)).$data;

    $path = tempnam(sys_get_temp_dir(), 'gloss').'.wav';
    file_put_contents($path, $wav);

    return new UploadedFile($path, $name, 'audio/wav', null, true);
}

it('maps each §22 slot onto the §30 media kind it accepts', function () {
    expect(StoreGlossaryMediaAction::SLOTS)->toBe([
        'audio_media_id' => 'audio',
        'image_media_id' => 'image',
        // A diagram is an image; §22 lists it separately because it means
        // something different to a reader, not because it is a different file.
        'example_audio_media_id' => 'audio',
        'diagram_media_id' => 'image',
    ]);

    expect(StoreGlossaryMediaAction::fileField('audio_media_id'))->toBe('audio_file')
        ->and(StoreGlossaryMediaAction::fileField('example_audio_media_id'))->toBe('example_audio_file');
});

it('uploads a term recording through the centralized media system', function () {
    Storage::fake('local');
    $admin = actingPeopleAdmin(['courses.manage']);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/glossary', [
            'term' => 'fatha',
            'term_ar' => 'فتحة',
            'meaning_primary' => 'A short a vowel mark.',
            'audio_file' => glossaryMediaAudio(),
        ])
        ->assertRedirect();

    expect((int) GlossaryItem::query()->latest('id')->value('audio_media_id'))->toBeGreaterThan(0);
});

it('refuses a file that is not the kind the slot takes', function () {
    Storage::fake('local');
    $admin = actingPeopleAdmin(['courses.manage']);

    // An image in the pronunciation slot. §30's per-kind mime list is what says
    // no, reused from `ContentBlockType` rather than restated here.
    expect(fn () => app(SaveGlossaryItemAction::class)->execute([
        'term' => 'wrong kind',
        'audio_file' => UploadedFile::fake()->create('picture.png', 8, 'image/png'),
    ]))->toThrow(ValidationException::class);

    expect(GlossaryItem::query()->count())->toBe(0);
});

it('keeps a recording when an edit does not mention it, and clears it on request', function () {
    Storage::fake('local');
    $admin = actingPeopleAdmin(['courses.manage']);

    $item = app(SaveGlossaryItemAction::class)->execute([
        'term' => 'kasra',
        'audio_file' => glossaryMediaAudio('kasra.wav'),
        'created_by' => $admin->id,
    ]);
    $mediaId = (int) $item->audio_media_id;
    expect($mediaId)->toBeGreaterThan(0);

    // An edit that says nothing about the audio must not delete the audio.
    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->put("/catalog/glossary/{$item->id}", ['term' => 'kasra renamed'])
        ->assertRedirect();

    expect((int) $item->fresh()->audio_media_id)->toBe($mediaId)
        ->and($item->fresh()->term)->toBe('kasra renamed');

    // Removing one has to be possible without replacing it, so clearing needs a
    // signal of its own rather than an empty upload.
    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->put("/catalog/glossary/{$item->id}", [
            'term' => 'kasra renamed',
            'clear_media' => ['audio_media_id'],
        ])
        ->assertRedirect();

    expect($item->fresh()->audio_media_id)->toBeNull();
});

it('lets a student hear the term on a lesson they can reach, and nobody else', function () {
    Storage::fake('local');
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = glossaryMediaLesson();

    $item = app(SaveGlossaryItemAction::class)->execute([
        'term' => 'fatha',
        'meaning_primary' => 'A short a vowel mark.',
        'audio_file' => glossaryMediaAudio(),
        'created_by' => $admin->id,
    ]);
    $mediaId = (int) $item->audio_media_id;
    app(AttachLessonGlossaryItemAction::class)->execute($lesson, $item->id, true);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Glossary', 'last_name' => 'Pupil']);
    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id, null);

    // Glossary media hangs off the term, not off a content block, so the old
    // gate refused a student the recording for a term on the lesson they were
    // reading.
    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get("/learn/media/{$mediaId}")
        ->assertOk();

    $stranger = User::factory()->create();
    makeStudent(['user_id' => $stranger->id, 'first_name' => 'Not', 'last_name' => 'Enrolled']);

    $this->actingAs($stranger)
        ->withoutLocalizationMiddleware()
        ->get("/learn/media/{$mediaId}")
        ->assertForbidden();
});

it('draws the media in the player and offers an upload in the bank', function () {
    // Both ends were shut; fixing one without the other would have left the
    // column exactly as dead as it was.
    $player = (string) file_get_contents(base_path('resources/js/Pages/Courses/Player/Show.jsx'));
    $bank = (string) file_get_contents(base_path('resources/js/Pages/Courses/Catalog/Glossary.jsx'));

    expect($player)->toContain('TermMedia')
        ->and($player)->toContain('audio_media_id')
        ->and($player)->toContain('example_audio_media_id')
        ->and($player)->toContain('diagram_media_id')
        ->and($bank)->toContain('audio_file')
        ->and($bank)->toContain('example_audio_file')
        ->and($bank)->toContain('diagram_file');
});
