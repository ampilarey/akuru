<?php

use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\StoreMediaContentBlockAction;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * SPEC §53, Phase 1A test 6: *"Test each Phase 1A block type using seeded
 * dynamic data"* through the lesson player — text, rich text, image, audio,
 * video, PDF, instruction. Six of the seven were rendered somewhere across
 * five files; rich text was pinned only at the block level and instruction
 * not at all (1A audit D4, STATUS §5fg). One lesson with all seven, read
 * back from the published snapshot in the order they were saved.
 */
it('renders all seven Phase 1A block types from one published revision, in order', function () {
    Storage::fake('local');
    Queue::fake();

    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Every block',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute(['course_id' => $course->id, 'title' => 'One', 'created_by' => $admin->id]);
    $lesson = app(SaveLessonAction::class)->execute(['course_module_id' => $module->id, 'title' => 'All seven', 'created_by' => $admin->id]);

    $save = fn (string $type, array $data) => app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => $type, 'data' => $data, 'created_by' => $admin->id,
    ]);
    $media = fn (string $type, UploadedFile $file) => app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => $type, 'file' => $file, 'created_by' => $admin->id,
    ]);

    $save('text', ['body' => 'Plain words']);
    $save('rich_text', ['html' => '<p>Rich <strong>words</strong></p>']);
    $media('image', UploadedFile::fake()->image('a.jpg', 8, 8));
    $media('audio', UploadedFile::fake()->create('a.mp3', 8, 'audio/mpeg'));
    $save('video', ['embed_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
    $media('pdf', UploadedFile::fake()->create('a.pdf', 8, 'application/pdf'));
    $save('instruction', ['body' => 'Read aloud', 'tone' => 'note']);

    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.player.show', $lesson))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Player/Show')
            ->has('snapshot.blocks', 7)
            ->where('snapshot.blocks.0.type', 'text')
            ->where('snapshot.blocks.0.data.body', 'Plain words')
            ->where('snapshot.blocks.1.type', 'rich_text')
            ->where('snapshot.blocks.2.type', 'image')
            ->where('snapshot.blocks.3.type', 'audio')
            ->where('snapshot.blocks.4.type', 'video')
            ->where('snapshot.blocks.5.type', 'pdf')
            ->where('snapshot.blocks.6.type', 'instruction')
            ->where('snapshot.blocks.6.data.body', 'Read aloud'));
});
