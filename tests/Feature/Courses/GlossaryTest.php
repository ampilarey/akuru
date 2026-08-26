<?php

use App\Domains\Courses\Actions\AttachLessonGlossaryItemAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveGlossaryItemAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\GlossaryItem;
use App\Domains\Courses\Models\LessonGlossaryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeGlossaryLesson(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Glossary Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Unit 1',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Vowels',
        'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'The fatha is a short a.'],
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'course', 'module', 'lesson');
}

it('creates a trilingual glossary term and exports csv', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $subjectId = CourseSubject::query()->where('slug', 'nahw')->value('id');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.glossary.store'), [
            'term' => 'fatha',
            'term_dv' => 'ފަތްހާ',
            'term_ar' => 'فتحة',
            'transliteration' => 'fatḥah',
            'meaning_primary' => 'A short a vowel mark.',
            'meaning_dv' => 'ކުރު a',
            'meaning_ar' => 'فتحة قصيرة',
            'description' => 'Written as a small diagonal line above the letter.',
            'example_text' => 'بَ',
            'example_translation' => 'ba',
            'tags' => 'arabic, vowels',
            'subject_id' => $subjectId,
        ])
        ->assertRedirect(route('catalog.glossary.index'));

    $item = GlossaryItem::query()->where('term', 'fatha')->sole();
    expect($item->term_ar)->toBe('فتحة')
        ->and($item->tags)->toBe(['arabic', 'vowels'])
        ->and($item->subject_id)->toBe($subjectId)
        ->and($item->created_by)->toBe($admin->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.glossary.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Glossary')
            ->has('rows', 1)
            ->where('rows.0.term', 'fatha')
            ->where('rows.0.term_ar', 'فتحة')
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.glossary.export'));
    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toStartWith('text/csv')
        ->and($csv->streamedContent())->toContain('fatha')
        ->and($csv->streamedContent())->toContain('فتحة');
});

it('updates and deletes a glossary term', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $item = app(SaveGlossaryItemAction::class)->execute([
        'term' => 'kasra',
        'meaning_primary' => 'short i',
        'created_by' => $admin->id,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('catalog.glossary.update', $item), [
            'term' => 'kasra',
            'meaning_primary' => 'A short i vowel mark.',
        ])
        ->assertRedirect(route('catalog.glossary.index'));

    expect($item->fresh()->meaning_primary)->toBe('A short i vowel mark.');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->delete(route('catalog.glossary.destroy', $item))
        ->assertRedirect(route('catalog.glossary.index'));

    expect(GlossaryItem::query()->find($item->id))->toBeNull();
});

it('forbids glossary screens without courses.manage', function () {
    $other = actingPeopleAdmin(['hr.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->get(route('catalog.glossary.index'))
        ->assertForbidden();
});

it('attaches terms to a lesson, snapshots them on publish, and shows them in the player', function () {
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = makeGlossaryLesson();
    $item = app(SaveGlossaryItemAction::class)->execute([
        'term' => 'fatha',
        'term_ar' => 'فتحة',
        'meaning_primary' => 'A short a vowel mark.',
        'created_by' => $admin->id,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.courses.lessons.glossary.attach', [$course->id, $lesson->id]), [
            'glossary_item_id' => $item->id,
            'is_required' => true,
        ])
        ->assertRedirect(route('catalog.courses.outline', $course->id));

    expect(LessonGlossaryItem::query()->where('lesson_id', $lesson->id)->count())->toBe(1);

    expect(fn () => app(AttachLessonGlossaryItemAction::class)->execute($lesson, $item->id))
        ->toThrow(ValidationException::class);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.courses.outline', $course->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Outline')
            ->has('glossaryItems', 1)
            ->where('modules.0.lessons.0.glossary.0.term', 'fatha')
            ->where('modules.0.lessons.0.glossary.0.is_required', true)
        );

    $revision = app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    expect($revision->snapshot_json['glossary'])->toHaveCount(1)
        ->and($revision->snapshot_json['glossary'][0]['term'])->toBe('fatha')
        ->and($revision->snapshot_json['glossary'][0]['term_ar'])->toBe('فتحة')
        ->and($revision->snapshot_json['glossary'][0]['is_required'])->toBeTrue()
        ->and($revision->snapshot_json['blocks'][0]['data']['body'])->toContain('fatha');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.player.show', $lesson))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Player/Show')
            ->where('snapshot.glossary.0.term', 'fatha')
            ->where('snapshot.glossary.0.meaning_primary', 'A short a vowel mark.')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->delete(route('catalog.courses.lessons.glossary.detach', [$course->id, $lesson->id, $item->id]))
        ->assertRedirect(route('catalog.courses.outline', $course->id));

    expect(LessonGlossaryItem::query()->where('lesson_id', $lesson->id)->count())->toBe(0);

    $still = app(\App\Domains\Courses\Actions\ResolvePublishedLessonAction::class)->execute($lesson->id);
    expect($still['glossary'][0]['term'])->toBe('fatha');
});
