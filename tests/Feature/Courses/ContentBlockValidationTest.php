<?php

use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ResolvePublishedLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function makeBlankLesson(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Block Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Intro',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Letters',
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'course', 'lesson');
}

it('accepts text, rich text, and instruction blocks and rejects unknown types', function () {
    ['lesson' => $lesson, 'admin' => $admin] = makeBlankLesson();

    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Alif'],
        'settings' => ['direction' => 'rtl'],
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'rich_text',
        'data' => ['html' => '<p>Ba <strong>ta</strong></p><script>alert(1)</script>'],
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'instruction',
        'data' => ['body' => 'Read aloud', 'tone' => 'tip'],
    ]);

    expect(fn () => app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'flashcard',
        'data' => ['body' => 'nope'],
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'image',
        'data' => ['body' => 'nope'],
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => ''],
    ]))->toThrow(ValidationException::class);

    $revision = app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    $snapshot = app(ResolvePublishedLessonAction::class)->execute($lesson->id);

    expect($snapshot['blocks'][0]['settings']['direction'])->toBe('rtl')
        ->and($snapshot['blocks'][1]['data']['html'])->toContain('<strong>ta</strong>')
        ->and($snapshot['blocks'][1]['data']['html'])->not->toContain('script')
        ->and($snapshot['blocks'][2]['data']['tone'])->toBe('tip')
        ->and($revision->revision_number)->toBe(1);
});
