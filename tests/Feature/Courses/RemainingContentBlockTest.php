<?php

use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ResolvePublishedLessonAction;
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
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeRemainingBlockLesson(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => '1B.4 Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Practice',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Blocks',
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'course', 'lesson');
}

it('stores glossary term dialogue flashcard download and embed blocks', function () {
    Storage::fake('local');
    Queue::fake();
    ['lesson' => $lesson, 'admin' => $admin, 'course' => $course] = makeRemainingBlockLesson();

    $glossary = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'glossary',
        'data' => [
            'entries' => [
                ['term' => 'Ism', 'definition' => 'A noun'],
                ['term' => 'Fi‘l', 'definition' => 'A verb'],
            ],
        ],
        'settings' => ['direction' => 'rtl'],
        'created_by' => $admin->id,
    ]);
    $term = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'term',
        'data' => ['term' => 'Harf', 'definition' => 'A particle'],
        'created_by' => $admin->id,
    ]);
    $dialogue = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'dialogue',
        'data' => [
            'lines' => [
                ['speaker' => 'A', 'text' => 'As-salamu alaykum'],
                ['speaker' => 'B', 'text' => 'Wa alaykum as-salam'],
            ],
        ],
        'created_by' => $admin->id,
    ]);
    $flashcard = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'flashcard',
        'data' => [
            'cards' => [
                ['front' => 'kitab', 'back' => 'book'],
                ['front' => 'qalam', 'back' => 'pen'],
            ],
        ],
        'created_by' => $admin->id,
    ]);
    $download = app(StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'download',
        'file' => UploadedFile::fake()->create('worksheet.pdf', 12, 'application/pdf'),
        'created_by' => $admin->id,
    ]);
    $quiz = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'quiz_embed',
        'data' => ['quiz_id' => 41, 'title' => 'Week 1 check'],
        'created_by' => $admin->id,
    ]);
    $assignment = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'assignment_embed',
        'data' => ['url' => 'https://example.com/homework', 'title' => 'Write five sentences'],
        'created_by' => $admin->id,
    ]);

    expect($glossary->data['entries'])->toHaveCount(2)
        ->and($term->data['entries'][0]['term'])->toBe('Harf')
        ->and($dialogue->data['lines'])->toHaveCount(2)
        ->and($flashcard->data['cards'][1]['back'])->toBe('pen')
        ->and($download->data['media_id'])->toBeInt()
        ->and($download->data)->not->toHaveKey('url')
        ->and($quiz->data['quiz_id'])->toBe(41)
        ->and($assignment->data['url'])->toBe('https://example.com/homework');

    expect(fn () => app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'glossary',
        'data' => ['entries' => [['term' => '', 'definition' => '']]],
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'quiz_embed',
        'data' => ['title' => 'Missing target'],
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'assignment_embed',
        'data' => ['url' => 'http://insecure.example/work'],
    ]))->toThrow(ValidationException::class);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.courses.blocks.store', $course->id), [
            'lesson_id' => $lesson->id,
            'type' => 'glossary',
            'term' => 'Mubtada',
            'definition' => 'The subject of a nominal sentence',
            'direction' => 'rtl',
        ])
        ->assertRedirect(route('catalog.courses.outline', $course->id));

    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    $snapshot = app(ResolvePublishedLessonAction::class)->execute($lesson->id);

    expect(collect($snapshot['blocks'])->pluck('type')->all())->toContain('glossary', 'term', 'dialogue', 'flashcard', 'download', 'quiz_embed', 'assignment_embed');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.player.show', $lesson->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Player/Show')
            ->has('snapshot.blocks'));
});
