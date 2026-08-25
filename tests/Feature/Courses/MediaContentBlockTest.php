<?php

use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ResolvePublishedLessonAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\StoreMediaContentBlockAction;
use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Media\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeMediaLesson(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Media Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Listen',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Sounds',
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'course', 'lesson');
}

it('stores image audio video and pdf blocks against private media ids', function () {
    Storage::fake('local');
    Queue::fake();
    ['lesson' => $lesson, 'admin' => $admin] = makeMediaLesson();

    $image = app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'image',
        'file' => UploadedFile::fake()->image('chart.png', 12, 12),
        'created_by' => $admin->id,
    ]);
    $audio = app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'audio',
        'file' => UploadedFile::fake()->create('lecture.mp3', 20, 'audio/mpeg'),
        'created_by' => $admin->id,
    ]);
    $video = app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'video',
        'file' => UploadedFile::fake()->create('clip.mp4', 40, 'video/mp4'),
        'created_by' => $admin->id,
    ]);
    $pdf = app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'pdf',
        'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        'created_by' => $admin->id,
    ]);
    $embed = app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'video',
        'embed_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'created_by' => $admin->id,
    ]);

    expect($image->data['media_id'])->toBeInt()
        ->and($image->data)->not->toHaveKey('url')
        ->and($audio->data['mime'])->toBe('audio/mpeg')
        ->and($video->data['original_name'])->toBe('clip.mp4')
        ->and($pdf->data['mime'])->toBe('application/pdf')
        ->and($embed->data['embed_url'])->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');

    $media = MediaFile::query()->findOrFail($image->data['media_id']);
    expect($media->visibility)->toBe('private')
        ->and($media->disk)->toBe('local');

    $revision = app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    $snapshot = app(ResolvePublishedLessonAction::class)->execute($lesson->id);

    expect($snapshot['blocks'][0]['data']['media_id'])->toBe($image->data['media_id'])
        ->and($snapshot['blocks'][4]['data']['embed_url'])->toContain('/embed/')
        ->and($revision->revision_number)->toBe(1);
});

it('rejects the wrong mime for a media block type', function () {
    Storage::fake('local');
    ['lesson' => $lesson, 'admin' => $admin] = makeMediaLesson();

    expect(fn () => app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'image',
        'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        'created_by' => $admin->id,
    ]))->toThrow(ValidationException::class);

    expect(ContentBlock::query()->count())->toBe(0);
});

it('serves private media only to authorized catalog staff', function () {
    Storage::fake('local');
    Queue::fake();
    ['lesson' => $lesson, 'admin' => $admin, 'course' => $course] = makeMediaLesson();

    $block = app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'pdf',
        'file' => UploadedFile::fake()->create('handout.pdf', 8, 'application/pdf'),
        'created_by' => $admin->id,
    ]);

    $this->withoutLocalizationMiddleware()
        ->get(route('catalog.media.show', $block->data['media_id']))
        ->assertRedirect();

    $served = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.media.show', $block->data['media_id']));

    $served->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($served->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.courses.blocks.store', $course->id), [
            'lesson_id' => $lesson->id,
            'type' => 'image',
            'file' => UploadedFile::fake()->image('ui.jpg', 8, 8),
        ])
        ->assertRedirect(route('catalog.courses.outline', $course->id));

    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.player.show', $lesson))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Player/Show')
            ->where('mediaShowUrl', '/catalog/media')
            ->has('snapshot.blocks', 2)
        );
});
