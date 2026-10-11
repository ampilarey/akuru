<?php

use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Media\Actions\StorePrivateMediaAction;
use App\Domains\Media\Actions\StorePublicMediaAction;
use App\Domains\Media\Actions\StoreUploadedDocumentAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * An upload refused, in Dhivehi and Arabic (BACKLOG C21, slice MD1, STATUS
 * §5ri).
 *
 * Every upload form stores its file through one of three Media actions, and
 * each says why it refused a file: the kind of file, its size, or a document
 * that is not a PDF, JPEG or PNG. Those sentences were English on every
 * page, and the kind of file was told as its MIME type (*File type
 * application/pdf is not allowed for this block.*).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();
    app()->setLocale('dv');
});

function md1Files(): array
{
    return [
        'app/Domains/Media/Actions/StorePrivateMediaAction.php',
        'app/Domains/Media/Actions/StorePublicMediaAction.php',
        'app/Domains/Media/Actions/StoreUploadedDocumentAction.php',
    ];
}

/** @return array<string, list<string>> */
function md1Refusal(callable $call): array
{
    try {
        $call();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('leaves no upload refusal in English, and keys each in three languages', function () {
    foreach (md1Files() as $file) {
        // A caller's own mistake, never a person's: no page names a directory.
        $found = array_filter(refusalEnglishIn($file), fn (string $line) => ! str_contains($line, 'Directory is not allowed.'));
        expect(array_values($found))->toBe([], $file);
    }

    foreach (['upload_error_type', 'upload_error_size', 'upload_error_document_type'] as $phrase) {
        expect((require base_path('resources/lang/en/common.php'))[$phrase] ?? null)->not->toBeNull($phrase)
            ->and((require base_path('resources/lang/dv/common.php'))[$phrase] ?? '')->toMatch('/\p{Thaana}/u', $phrase)
            ->and((require base_path('resources/lang/ar/common.php'))[$phrase] ?? '')->toMatch('/\p{Arabic}/u', $phrase);
    }
    expect(__('common.upload_error_size', ['size' => 5], 'dv'))->toContain('5')
        ->and(__('common.upload_error_size', ['size' => 5], 'ar'))->toContain('5');
});

it('refuses a file of the wrong kind or size in Dhivehi, without its MIME type', function () {
    $pdf = fn () => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf');

    $type = md1Refusal(fn () => app(StorePrivateMediaAction::class)->execute($pdf(), null, ['image/png']));
    expect($type)->toBe(['file' => [__('common.upload_error_type', [], 'dv')]])
        ->and($type['file'][0])->not->toContain('application/pdf');

    expect(md1Refusal(fn () => app(StorePrivateMediaAction::class)->execute(UploadedFile::fake()->create('big.pdf', 3 * 1024, 'application/pdf'), null, [], 2 * 1048576)))
        ->toBe(['file' => [__('common.upload_error_size', ['size' => 2], 'dv')]]);

    expect(md1Refusal(fn () => app(StorePublicMediaAction::class)->execute($pdf())))
        ->toBe(['file' => [__('common.upload_error_type', [], 'dv')]]);

    expect(md1Refusal(fn () => app(StoreUploadedDocumentAction::class)->execute(UploadedFile::fake()->create('song.mp3', 10, 'audio/mpeg'), 'student', 1, 'Note')))
        ->toBe(['document' => [__('common.upload_error_document_type', [], 'dv')]])
        ->and(md1Refusal(fn () => app(StoreUploadedDocumentAction::class)->execute(UploadedFile::fake()->create('scan.pdf', 6 * 1024, 'application/pdf'), 'student', 1, 'Note')))
        ->toBe(['document' => [__('common.upload_error_size', ['size' => 5], 'dv')]]);
});

it('says an image block given a PDF is the wrong kind of file, in Arabic', function () {
    app()->setLocale('ar');
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'MD1 Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute(['course_id' => $course->id, 'title' => 'One', 'created_by' => $admin->id]);
    $lesson = app(SaveLessonAction::class)->execute(['course_module_id' => $module->id, 'title' => 'First', 'created_by' => $admin->id]);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->from(route('catalog.courses.outline', $course->id))
        ->post(route('catalog.courses.blocks.store', $course->id), [
            'lesson_id' => $lesson->id,
            'type' => 'image',
            'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['file' => __('common.upload_error_type', [], 'ar')]);
});
